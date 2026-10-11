@use('App\Support\Format')

@component('emails.layout', ['title' => 'Undangan akun SIM-KER'])
    <p style="margin:0 0 16px 0;">Halo {{ $user->name }},</p>
    <p style="margin:0 0 16px 0;">Admin Distrik Ransiki membuatkan akun SIM-KER untuk Anda dengan username <strong>{{ $user->username }}</strong>. Buat kata sandi Anda sendiri lewat tombol di bawah.</p>
    @include('emails._button', ['url' => $url, 'label' => 'Buat kata sandi'])
    <p style="margin:0 0 16px 0;">Tautan ini hanya bisa dipakai sekali dan berlaku sampai {{ Format::dateTime($expiresAt) }} WIT. Bila sudah lewat, minta Admin distrik mengirim ulang undangan.</p>
    <p style="margin:0;color:#4A504D;">Bila Anda tidak merasa terkait dengan SIM-KER, abaikan email ini.</p>
@endcomponent
