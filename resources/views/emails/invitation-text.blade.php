@use('App\Support\Format')
Halo {!! $user->name !!},

Admin Distrik Ransiki membuatkan akun SIM-KER untuk Anda dengan username {!! $user->username !!}.
Buat kata sandi Anda sendiri lewat tautan ini:

{!! $url !!}

Tautan hanya bisa dipakai sekali dan berlaku sampai {!! Format::dateTime($expiresAt) !!} WIT. Bila sudah lewat, minta Admin distrik mengirim ulang undangan.

Bila Anda tidak merasa terkait dengan SIM-KER, abaikan email ini.

SIM-KER Distrik Ransiki
