@component('emails.layout', ['title' => 'Atur ulang kata sandi'])
    <p style="margin:0 0 16px 0;">Halo {{ $user->name }},</p>
    <p style="margin:0 0 16px 0;">Ada permintaan untuk mengatur ulang kata sandi akun SIM-KER dengan username <strong>{{ $user->username }}</strong>.</p>
    @include('emails._button', ['url' => $url, 'label' => 'Atur ulang kata sandi'])
    <p style="margin:0 0 16px 0;">Tautan ini berlaku {{ $minutes }} menit dan hanya bisa dipakai sekali.</p>
    <p style="margin:0;color:#4A504D;">Bila Anda tidak meminta ini, abaikan email ini. Kata sandi Anda tidak berubah.</p>
@endcomponent
