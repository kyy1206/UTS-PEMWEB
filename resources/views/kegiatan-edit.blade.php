<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ubah Kegiatan Mahasiswa</title>
</head>
<body>
    <h1>Ubah Kegiatan Mahasiswa</h1>

    @if (session('gagal'))
        <p>{{ session('gagal') }}</p>
    @endif

    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form action="{{ route('kegiatan.update', $kegiatan['id']) }}" method="POST" novalidate>
        @csrf
        @method('PUT')
        <p>Kode Kegiatan (10 Alfanumerik)<br>
            <input type="text" name="kode_kegiatan" value="{{ old('kode_kegiatan', $kegiatan['kode_kegiatan']) }}">
        </p>
        <p>Nama Kegiatan<br>
            <input type="text" name="nama_kegiatan" value="{{ old('nama_kegiatan', $kegiatan['nama_kegiatan']) }}">
        </p>
        <p>Email PIC<br>
            <input type="email" name="email_pic" value="{{ old('email_pic', $kegiatan['email_pic']) }}">
        </p>
        <p>Kuota (10 - 200)<br>
            <input type="number" name="kuota" value="{{ old('kuota', $kegiatan['kuota']) }}">
        </p>
        <p>Jenis Kegiatan<br>
            <select name="jenis_kegiatan_id">
                @foreach ($daftarJenisKegiatan as $jenis)
                    <option value="{{ $jenis['id'] }}" @selected(old('jenis_kegiatan_id', $kegiatan['jenis_kegiatan_id']) == $jenis['id'])>
                        {{ $jenis['nama_jenis'] }}
                    </option>
                @endforeach
            </select>
        </p>
        <button type="submit">Simpan Perubahan</button>
        <a href="{{ route('kegiatan.index') }}">Batal</a>
    </form>
</body>
</html>