<?php

namespace Tests\Feature;

use App\Actions\Incidents\TransitionIncidentStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\AppNotification;
use App\Models\CaseStatus;
use App\Models\Incident;
use App\Models\RiskLevel;
use App\Models\StatusHistory;
use App\Models\User;
use Database\Seeders\CaseStatusSeeder;
use Database\Seeders\IncidentTypeSeeder;
use Database\Seeders\RiskLevelSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class Pbi13IncidentVerificationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, CaseStatusSeeder::class, IncidentTypeSeeder::class, RiskLevelSeeder::class]);
    }

    public function test_queue_shows_pending_reports_and_admin_can_review_private_evidence_without_starting(): void
    {
        $reported = $this->report('Reported', User::factory()->citizen()->create());
        $pending = $this->report('Under Verification', User::factory()->ranger()->create());
        $verified = $this->report('Verified');
        $rejected = $this->report('Rejected');
        $admin = User::factory()->admin()->create();

        config(['filesystems.evidence_disk' => 'local']);
        Storage::fake('local')->buildTemporaryUrlsUsing(
            fn ($path, $expiration) => url(URL::temporarySignedRoute('storage.local', $expiration, ['path' => $path], absolute: false)),
        );
        Storage::fake('public');
        $evidence = $reported->evidences()->create([
            'client_uuid' => Str::uuid()->toString(),
            'uploaded_by' => $reported->reporter_id,
            'file_path' => 'evidences/incident-reports/photo.png',
            'mime_type' => 'image/png',
            'file_size' => 10,
        ]);
        Storage::disk('local')->put($evidence->file_path, 'test image');

        $this->actingAs($admin)->get(route('admin.incidents.index', ['status' => 'needs-verification']))
            ->assertOk()->assertSee($reported->code)->assertSee($pending->code)
            ->assertDontSee($verified->code)->assertDontSee($rejected->code);

        $this->get(route('admin.incidents.show', $reported))
            ->assertOk()->assertSee('Dugaan')->assertSee($reported->description)
            ->assertSee((string) $reported->latitude)->assertSee(route('evidence.show', $evidence));
        $this->get(route('admin.incidents.show', $pending))->assertOk()->assertSee('Dugaan');
        $signedUrl = $this->get(route('evidence.show', $evidence))->assertRedirect()->headers->get('Location');
        $this->get($signedUrl)->assertOk()->assertStreamedContent('test image');

        $evidence->update(['file_path' => 'evidences/incidents/historical.png']);
        $this->get(route('evidence.show', $evidence))->assertNotFound();
        Storage::disk('public')->put($evidence->file_path, 'historical image');
        $this->get(route('evidence.show', $evidence))->assertOk()->assertStreamedContent('historical image');
        $this->actingAs($pending->reporter)->get(route('evidence.show', $evidence))->assertForbidden();

        $this->assertSame('Reported', $reported->fresh()->caseStatus->name);
        $this->assertDatabaseCount('status_histories', 0);
    }

    public function test_risk_must_be_active_and_match_the_options_shown_to_the_admin(): void
    {
        $incident = $this->report('Under Verification');
        $active = RiskLevel::active()->firstOrFail();
        $inactive = RiskLevel::create(['name' => 'Arsip risiko', 'sort_order' => 999, 'is_active' => false]);
        $this->actingAs(User::factory()->admin()->create());

        $detail = $this->get(route('admin.incidents.show', $incident))->assertOk()->assertDontSee($inactive->name);
        $snapshot = $detail->viewData('riskSnapshot');
        $originalName = $active->name;
        $active->update(['name' => 'Nama risiko terbaru']);

        foreach ([[], ['risk_level_id' => 9999], ['risk_level_id' => $inactive->id], ['risk_level_id' => $active->id], [
            'risk_level_id' => $active->id,
            'risk_snapshot' => $snapshot,
        ]] as $payload) {
            $this->postJson(route('admin.incidents.update-status', $incident), ['to_status' => 'Verified', ...$payload])
                ->assertUnprocessable()->assertJsonValidationErrors('risk_level_id');
        }

        $active->update(['name' => $originalName]);
        $this->postJson(route('admin.incidents.update-status', $incident), [
            'to_status' => 'Verified',
            'risk_level_id' => $active->id,
            'risk_snapshot' => $snapshot,
        ])->assertUnprocessable()->assertJsonValidationErrors('risk_level_id');

        RiskLevel::query()->update(['is_active' => false]);
        $this->get(route('admin.incidents.show', $incident))
            ->assertSee('Belum ada tingkat risiko aktif')->assertSee('Tolak Laporan Ini');
        $this->assertSame('Under Verification', $incident->fresh()->caseStatus->name);
        $this->assertNull($incident->fresh()->risk_level_id);
        $this->assertDatabaseCount('status_histories', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_first_decision_wins_and_only_the_selected_incident_changes(): void
    {
        $reporter = User::factory()->citizen()->create();
        $incident = $this->report('Reported', $reporter);
        $other = Incident::factory()->create([
            'reporter_id' => $reporter->id,
            'forest_area_id' => $incident->forest_area_id,
        ]);
        $otherBefore = $other->fresh()->getAttributes();
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $risk = RiskLevel::active()->firstOrFail();
        $statusRoute = route('admin.incidents.update-status', $incident);

        foreach ([User::factory()->ranger()->create(), $reporter] as $unauthorized) {
            $this->actingAs($unauthorized)->postJson($statusRoute, ['to_status' => 'Under Verification'])->assertForbidden();
        }
        $this->actingAs($admin)->postJson($statusRoute, ['to_status' => 'Verified', 'risk_level_id' => $risk->id])
            ->assertConflict();
        $this->assertDatabaseCount('status_histories', 0);

        $this->post($statusRoute, ['to_status' => 'Under Verification'])->assertSessionHasNoErrors();
        $start = $incident->statusHistories()->sole();
        $this->assertSame('Reported', $start->fromStatus->name);
        $this->assertSame('Under Verification', $start->toStatus->name);
        $this->assertSame($admin->id, $start->changed_by);
        $this->assertNotNull($start->changed_at);
        $this->assertDatabaseCount('notifications', 0);

        $detail = $this->get(route('admin.incidents.show', $incident));
        $this->post($statusRoute, [
            'to_status' => 'Verified',
            'risk_level_id' => $risk->id,
            'risk_snapshot' => $detail->viewData('riskSnapshot'),
        ])->assertSessionHasNoErrors();

        $this->actingAs($otherAdmin)->post($statusRoute, [
            'to_status' => 'Rejected', 'reject_reason' => 'Koordinat yang dikirim tidak sesuai.',
        ])->assertRedirect(route('admin.incidents.show', $incident))->assertSessionHasErrors('to_status');
        $this->postJson($statusRoute, ['to_status' => 'Verified', 'risk_level_id' => $risk->id])->assertConflict();

        $incident->refresh();
        $this->assertSame('Verified', $incident->caseStatus->name);
        $this->assertSame($risk->id, $incident->risk_level_id);
        $this->assertNull($incident->reject_reason);
        $this->assertSame($otherBefore, $other->fresh()->getAttributes());
        $this->assertDatabaseCount('status_histories', 2);
        $this->assertSame($admin->id, $incident->statusHistories()->where('from_status_id', $start->to_status_id)->sole()->changed_by);
        $notification = AppNotification::sole();
        $this->assertSame($reporter->id, $notification->user_id);
        $this->assertSame(['incident_id' => $incident->id, 'code' => $incident->code, 'status' => 'Verified'], $notification->data);
    }

    public function test_rejection_reason_is_required_trimmed_and_visible_to_the_reporter_as_text(): void
    {
        $ranger = User::factory()->ranger()->create();
        $incident = $this->report('Under Verification', $ranger);
        $statusRoute = route('admin.incidents.update-status', $incident);
        $this->actingAs(User::factory()->admin()->create());

        foreach (['', '   ', str_repeat('x', 1001)] as $reason) {
            $this->postJson($statusRoute, ['to_status' => 'Rejected', 'reject_reason' => $reason])
                ->assertUnprocessable()->assertJsonValidationErrors('reject_reason');
        }
        $this->assertDatabaseCount('status_histories', 0);
        $this->assertSame('Under Verification', $incident->fresh()->caseStatus->name);

        $reason = '<b>Bukti tidak sesuai.</b>';
        $this->post($statusRoute, ['to_status' => 'Rejected', 'reject_reason' => '  '.$reason.'  '])
            ->assertSessionHasNoErrors();
        $this->assertSame('Rejected', $incident->fresh()->caseStatus->name);
        $this->assertSame($reason, $incident->fresh()->reject_reason);
        $this->assertSame($reason, $incident->statusHistories()->sole()->note);
        $this->assertSame('Rejected', $ranger->appNotifications()->sole()->data['status']);

        $detail = route('ranger.incidents.show', $incident);
        $this->actingAs($ranger)->get($detail)->assertOk()->assertSee($reason)->assertDontSee($reason, false);
        $this->actingAs(User::factory()->ranger()->create())->get($detail)->assertNotFound();
    }

    public function test_history_failure_and_outer_rollback_do_not_leave_decisions_or_notifications(): void
    {
        $admin = User::factory()->admin()->create();
        $incident = $this->report('Under Verification');
        $action = app(TransitionIncidentStatus::class);
        $risk = RiskLevel::active()->firstOrFail();

        DB::beginTransaction();
        try {
            $action->handle($admin, $incident, 'Verified', ['risk_level_id' => $risk->id]);
            $this->assertSame('Verified', $incident->fresh()->caseStatus->name);
            $this->assertDatabaseCount('status_histories', 1);
            $this->assertDatabaseCount('notifications', 0);
        } finally {
            DB::rollBack();
        }

        Event::listen('eloquent.creating: '.StatusHistory::class, function (): never {
            throw new RuntimeException('Riwayat gagal disimpan.');
        });

        try {
            $action->handle($admin, $incident, 'Rejected', ['reject_reason' => 'Bukti belum cukup jelas.']);
            $this->fail('Kegagalan riwayat seharusnya diteruskan.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Riwayat gagal disimpan.', $exception->getMessage());
        }

        $incident->refresh();
        $this->assertSame('Under Verification', $incident->caseStatus->name);
        $this->assertNull($incident->risk_level_id);
        $this->assertNull($incident->reject_reason);
        $this->assertDatabaseCount('status_histories', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_parallel_admin_decisions_on_postgresql_produce_one_history_and_notification(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Pengujian konkurensi memerlukan PostgreSQL.');
        }

        $incidentId = $this->report('Under Verification')->id;
        $adminId = User::factory()->admin()->create()->id;
        $otherAdminId = User::factory()->admin()->create()->id;
        $riskId = RiskLevel::active()->firstOrFail()->id;

        $decide = static function (int $actorId, string $status, array $data) use ($incidentId): string {
            try {
                app(TransitionIncidentStatus::class)->handle(
                    User::findOrFail($actorId),
                    Incident::findOrFail($incidentId),
                    $status,
                    $data,
                );
            } catch (InvalidStatusTransitionException) {
                return 'conflict';
            }

            return $status;
        };

        $outcomes = Concurrency::driver('process')->run([
            static fn () => $decide($adminId, 'Verified', ['risk_level_id' => $riskId]),
            static fn () => $decide($otherAdminId, 'Rejected', ['reject_reason' => 'Bukti belum cukup jelas.']),
        ]);

        $incident = Incident::findOrFail($incidentId);
        $history = $incident->statusHistories()->sole();
        $status = $incident->caseStatus->name;
        $this->assertEqualsCanonicalizing(['conflict', $status], $outcomes);
        $this->assertSame($incident->case_status_id, $history->to_status_id);
        $this->assertSame($status === 'Verified' ? $adminId : $otherAdminId, $history->changed_by);
        $this->assertSame($status, AppNotification::sole()->data['status']);
    }

    private function report(string $status, ?User $reporter = null): Incident
    {
        return Incident::factory()->create([
            'reporter_id' => $reporter?->id ?? User::factory()->citizen(),
            'case_status_id' => CaseStatus::where('name', $status)->sole()->id,
        ]);
    }
}
