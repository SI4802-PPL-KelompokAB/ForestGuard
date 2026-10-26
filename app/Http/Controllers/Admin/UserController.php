<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\CreateManagedUser;
use App\Actions\Admin\RestoreManagedUser;
use App\Actions\Admin\SoftDeleteManagedUser;
use App\Actions\Admin\UpdateManagedUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DestroyUserRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $peran = $request->string('peran')->toString();
        $status = $request->string('status')->toString();
        $q = $request->string('q')->trim()->toString();

        $users = User::query()
            ->when(in_array($peran, [Role::ADMIN, Role::RANGER, Role::CITIZEN], true), function ($query) use ($peran): void {
                $query->whereHas('role', fn ($roleQuery) => $roleQuery->where('name', $peran));
            })
            ->when($status === 'terhapus', fn ($query) => $query->onlyTrashed())
            ->when($status === 'aktif', fn ($query) => $query->where('is_active', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('is_active', false))
            ->when($q !== '', function ($query) use ($q): void {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(function ($query) use ($like): void {
                    $query->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'peran' => $peran,
            'status' => $status,
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'roles' => Role::options(),
        ]);
    }

    public function store(StoreUserRequest $request, CreateManagedUser $createManagedUser): RedirectResponse
    {
        $result = $createManagedUser->handle($request);

        if (! $result['email_sent']) {
            return redirect()
                ->route('admin.users.index')
                ->with('status', 'Akun berhasil dibuat.')
                ->with('warning', 'Email kata sandi gagal dikirim. Silakan tetapkan kata sandi manual melalui menu Ubah.');
        }

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Akun berhasil dibuat. Kata sandi sementara telah dikirim ke email.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'managedUser' => $user,
            'roles' => Role::options(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateManagedUser $updateManagedUser): RedirectResponse
    {
        $updateManagedUser->handle($request, $user);

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Pengguna berhasil diperbarui.');
    }

    public function destroy(DestroyUserRequest $request, User $user, SoftDeleteManagedUser $softDeleteManagedUser): RedirectResponse
    {
        $softDeleteManagedUser->handle($request->user(), $user);

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Pengguna berhasil dihapus.');
    }

    public function restore(User $user, RestoreManagedUser $restoreManagedUser): RedirectResponse
    {
        $restoreManagedUser->handle($user);

        return redirect()
            ->route('admin.users.index', ['status' => 'terhapus'])
            ->with('status', 'Pengguna berhasil dipulihkan.');
    }
}
