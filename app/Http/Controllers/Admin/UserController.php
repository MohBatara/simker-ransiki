<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\IssueTemporaryPassword;
use App\Actions\Users\SendInvitation;
use App\Enums\OrganizationStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Opd;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'role', 'status']);

        $inactiveCount = User::filter($filters)->where('is_active', false)->count();

        $users = User::filter($filters)
            ->with(['opd', 'organization'])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [...compact('users', 'filters', 'inactiveCount'), 'newUser' => new User, ...$this->options()]);
    }

    /**
     * Tambah akun kini berupa popup: alamat lama dialihkan ke daftar Pengguna dengan popup terbuka, atau ke tab Akun
     * detail kelompok bila membawa ?kelompok= (pintasan "Tambah akun" kelompok).
     */
    public function create(Request $request): RedirectResponse
    {
        $organization = Organization::find($request->integer('kelompok'));

        return $organization
            ? redirect()->route('admin.organizations.users', [$organization, 'tambah-akun' => 1])
            : redirect()->route('admin.users.index', ['tambah' => 1]);
    }

    public function store(UserRequest $request, IssueTemporaryPassword $issuePassword, SendInvitation $invite): RedirectResponse
    {
        // Kata sandi acak; diganti kata sandi sementara yang ditampilkan ke Admin, atau dibuat pengguna lewat undangan.
        $user = User::create([...$request->userData(), 'password' => str()->random(40)]);

        if ($request->wantsInvitation()) {
            return $this->sendInvitation($user, $invite, 'Akun dibuat.');
        }

        return $this->showCredentials($user, $issuePassword->handle($user), 'Akun dibuat.');
    }

    public function resendInvitation(User $user, SendInvitation $invite): RedirectResponse
    {
        return $this->sendInvitation($user, $invite, null);
    }

    public function show(User $user): View
    {
        $credentials = session('credentials');

        return view('admin.users.show', [
            ...$this->options(),
            'user' => $user->load(['opd', 'organization']),
            'temporaryPassword' => ($credentials['user_id'] ?? null) === $user->id
                ? Crypt::decryptString($credentials['password'])
                : null,
        ]);
    }

    /** Ubah akun kini berupa popup di detail akun; alamat lama dialihkan ke sana dengan popup terbuka. */
    public function edit(User $user): RedirectResponse
    {
        return redirect()->route('admin.users.show', [$user, 'ubah' => 1]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $user->update($request->userData());

        return redirect()->route('admin.users.show', $user)->with('status', 'Akun diperbarui.');
    }

    public function resetPassword(User $user, IssueTemporaryPassword $issuePassword): RedirectResponse
    {
        return $this->showCredentials($user, $issuePassword->handle($user), 'Kata sandi direset.');
    }

    public function activate(User $user): RedirectResponse
    {
        $user->update(['is_active' => true]);

        return back()->with('status', 'Akun diaktifkan.');
    }

    public function deactivate(User $user): RedirectResponse
    {
        // Middleware "active" mengeluarkan pengguna ini pada permintaan berikutnya.
        $user->update(['is_active' => false]);

        return back()->with('status', 'Akun dinonaktifkan.');
    }

    /** Gagal kirim (mis. SMTP salah) tidak membatalkan akun; Admin bisa kirim ulang atau memakai kata sandi sementara. */
    private function sendInvitation(User $user, SendInvitation $invite, ?string $prefix): RedirectResponse
    {
        $redirect = redirect()->route('admin.users.show', $user);

        try {
            $invite->handle($user);
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            return $redirect->with('error', trim($prefix.' Email undangan gagal dikirim. Periksa pengaturan email, lalu pilih Kirim ulang undangan atau Reset kata sandi.'));
        }

        return $redirect->with('status', trim($prefix.' Undangan dikirim ke '.$user->email.'.'));
    }

    /**
     * Kata sandi sementara ditampilkan sekali di halaman detail. Disimpan terenkripsi di flash session,
     * tidak pernah di database, dan hilang setelah halaman berikutnya dibuka.
     */
    private function showCredentials(User $user, string $password, string $status): RedirectResponse
    {
        return redirect()->route('admin.users.show', $user)
            ->with('status', $status)
            ->with('credentials', ['user_id' => $user->id, 'password' => Crypt::encryptString($password)]);
    }

    private function options(): array
    {
        return [
            'roles' => Role::options(),
            'opds' => Opd::orderBy('name')->pluck('name', 'id')->all(),
            'organizations' => Organization::with('village')->orderBy('name')->get()
                ->mapWithKeys(fn (Organization $organization) => [
                    $organization->id => $organization->name.' · '.$organization->village->name
                        .($organization->status === OrganizationStatus::Closed ? ' (ditutup)' : ''),
                ])->all(),
        ];
    }
}
