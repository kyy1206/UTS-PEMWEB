<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

if (!function_exists('statusKuota')) {
    function statusKuota(int $kuota): string {
        return $kuota >= 75 ? 'Besar' : 'Terbatas';
    }
}

Route::get('/', function () {
    return redirect()->route('kegiatan.index');
});

// 1. READ, PENCARIAN, DAN FILTER
Route::get('/kegiatan', function (Request $request) {
    $kataKunci = trim(strip_tags((string) $request->query('q', '')));
    $jenisKegiatanId = $request->query('jenis_kegiatan_id', '');

    try {
        $pdo = DB::connection()->getPdo();

        $sql = 'SELECT k.id, k.kode_kegiatan, k.nama_kegiatan, k.email_pic, 
                       k.kuota, k.jenis_kegiatan_id, j.nama_jenis
                FROM kegiatan AS k
                JOIN jenis_kegiatan AS j ON j.id = k.jenis_kegiatan_id
                WHERE 1 = 1';
        
        $parameter = [];

        if ($kataKunci !== '') {
            $sql .= ' AND (k.kode_kegiatan LIKE :q_kode 
                      OR k.nama_kegiatan LIKE :q_nama 
                      OR k.email_pic LIKE :q_email)';
            $nilaiCari = '%' . $kataKunci . '%';
            $parameter['q_kode'] = $nilaiCari;
            $parameter['q_nama'] = $nilaiCari;
            $parameter['q_email'] = $nilaiCari;
        }

        if ($jenisKegiatanId !== '') {
            $sql .= ' AND k.jenis_kegiatan_id = :jenis_kegiatan_id';
            $parameter['jenis_kegiatan_id'] = (int) $jenisKegiatanId;
        }

        $sql .= ' ORDER BY k.kode_kegiatan';

        $stmtKegiatan = $pdo->prepare($sql);
        $stmtKegiatan->execute($parameter);
        $daftarKegiatan = $stmtKegiatan->fetchAll(\PDO::FETCH_ASSOC);

        $stmtJenis = $pdo->prepare('SELECT id, nama_jenis FROM jenis_kegiatan ORDER BY nama_jenis');
        $stmtJenis->execute();
        $daftarJenisKegiatan = $stmtJenis->fetchAll(\PDO::FETCH_ASSOC);

        return view('kegiatan', compact(
            'daftarKegiatan',
            'daftarJenisKegiatan',
            'kataKunci',
            'jenisKegiatanId'
        ));
    } catch (\Throwable $error) {
        report($error);
        return response('Gagal memuat data kegiatan. Silakan periksa koneksi basis data.', 500);
    }
})->name('kegiatan.index');

// 2. CREATE (STORE)
Route::post('/kegiatan', function (Request $request) {
    $dataInput = [
        'kode_kegiatan' => strtoupper(trim(strip_tags((string) $request->input('kode_kegiatan')))),
        'nama_kegiatan' => trim(strip_tags((string) $request->input('nama_kegiatan'))),
        'email_pic' => filter_var(trim((string) $request->input('email_pic')), FILTER_SANITIZE_EMAIL),
        'kuota' => trim((string) $request->input('kuota')),
        'jenis_kegiatan_id' => trim((string) $request->input('jenis_kegiatan_id')),
    ];

    $validator = Validator::make($dataInput, [
        'kode_kegiatan' => ['required', 'string', 'size:10', 'alpha_num'],
        'nama_kegiatan' => ['required', 'string', 'min:3', 'max:100'],
        'email_pic' => ['required', 'email', 'max:100'],
        'kuota' => ['required', 'integer', 'between:10,200'],
        'jenis_kegiatan_id' => ['required', 'integer'],
    ], [
        'kode_kegiatan.required' => 'Kode kegiatan wajib diisi.',
        'kode_kegiatan.size' => 'Kode kegiatan harus tepat 10 karakter.',
        'kode_kegiatan.alpha_num' => 'Kode kegiatan harus berupa alfanumerik.',
        'nama_kegiatan.required' => 'Nama kegiatan wajib diisi.',
        'nama_kegiatan.min' => 'Nama kegiatan minimal 3 karakter.',
        'email_pic.required' => 'Email PIC wajib diisi.',
        'email_pic.email' => 'Format email PIC tidak valid.',
        'kuota.required' => 'Kuota wajib diisi.',
        'kuota.between' => 'Kuota harus berupa angka antara 10 hingga 200.',
        'jenis_kegiatan_id.required' => 'Jenis kegiatan wajib dipilih.',
    ]);

    if ($validator->fails()) {
        return back()->withErrors($validator)->withInput();
    }

    $valid = $validator->validated();

    try {
        $pdo = DB::connection()->getPdo();

        $stmtCek = $pdo->prepare(
            'SELECT COUNT(*) FROM kegiatan WHERE kode_kegiatan = :kode OR email_pic = :email'
        );
        $stmtCek->execute([
            'kode' => $valid['kode_kegiatan'],
            'email' => $valid['email_pic'],
        ]);

        if ((int) $stmtCek->fetchColumn() > 0) {
            return back()->withInput()->with('gagal', 'Kode kegiatan atau Email PIC sudah digunakan.');
        }

        $stmtSimpan = $pdo->prepare(
            'INSERT INTO kegiatan (kode_kegiatan, nama_kegiatan, email_pic, kuota, jenis_kegiatan_id)
             VALUES (:kode, :nama, :email, :kuota, :jenis_id)'
        );
        $stmtSimpan->execute([
            'kode' => $valid['kode_kegiatan'],
            'nama' => $valid['nama_kegiatan'],
            'email' => $valid['email_pic'],
            'kuota' => (int) $valid['kuota'],
            'jenis_id' => (int) $valid['jenis_kegiatan_id'],
        ]);

        return redirect()->route('kegiatan.index')->with('berhasil', 'Data kegiatan berhasil ditambahkan.');
    } catch (\Throwable $error) {
        report($error);
        return back()->withInput()->with('gagal', 'Data gagal disimpan akibat kesalahan sistem.');
    }
})->name('kegiatan.store');

// 3. EDIT FORM
Route::get('/kegiatan/{id}/edit', function (int $id) {
    try {
        $pdo = DB::connection()->getPdo();

        $stmt = $pdo->prepare(
            'SELECT id, kode_kegiatan, nama_kegiatan, email_pic, kuota, jenis_kegiatan_id 
             FROM kegiatan WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $kegiatan = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$kegiatan) {
            return redirect()->route('kegiatan.index')->with('gagal', 'Data kegiatan tidak ditemukan.');
        }

        $stmtJenis = $pdo->prepare('SELECT id, nama_jenis FROM jenis_kegiatan ORDER BY nama_jenis');
        $stmtJenis->execute();
        $daftarJenisKegiatan = $stmtJenis->fetchAll(\PDO::FETCH_ASSOC);

        return view('kegiatan-edit', compact('kegiatan', 'daftarJenisKegiatan'));
    } catch (\Throwable $error) {
        report($error);
        return redirect()->route('kegiatan.index')->with('gagal', 'Gagal memuat form edit.');
    }
})->name('kegiatan.edit');

// 4. UPDATE
Route::put('/kegiatan/{id}', function (Request $request, int $id) {
    $dataInput = [
        'kode_kegiatan' => strtoupper(trim(strip_tags((string) $request->input('kode_kegiatan')))),
        'nama_kegiatan' => trim(strip_tags((string) $request->input('nama_kegiatan'))),
        'email_pic' => filter_var(trim((string) $request->input('email_pic')), FILTER_SANITIZE_EMAIL),
        'kuota' => trim((string) $request->input('kuota')),
        'jenis_kegiatan_id' => trim((string) $request->input('jenis_kegiatan_id')),
    ];

    $validator = Validator::make($dataInput, [
        'kode_kegiatan' => ['required', 'string', 'size:10', 'alpha_num'],
        'nama_kegiatan' => ['required', 'string', 'min:3', 'max:100'],
        'email_pic' => ['required', 'email', 'max:100'],
        'kuota' => ['required', 'integer', 'between:10,200'],
        'jenis_kegiatan_id' => ['required', 'integer'],
    ], [
        'kode_kegiatan.required' => 'Kode kegiatan wajib diisi.',
        'kode_kegiatan.size' => 'Kode kegiatan harus tepat 10 karakter.',
        'kode_kegiatan.alpha_num' => 'Kode kegiatan harus berupa alfanumerik.',
        'nama_kegiatan.required' => 'Nama kegiatan wajib diisi.',
        'nama_kegiatan.min' => 'Nama kegiatan minimal 3 karakter.',
        'email_pic.required' => 'Email PIC wajib diisi.',
        'email_pic.email' => 'Format email PIC tidak valid.',
        'kuota.required' => 'Kuota wajib diisi.',
        'kuota.between' => 'Kuota harus berupa angka antara 10 hingga 200.',
        'jenis_kegiatan_id.required' => 'Jenis kegiatan wajib dipilih.',
    ]);

    if ($validator->fails()) {
        return back()->withErrors($validator)->withInput();
    }

    $valid = $validator->validated();

    try {
        $pdo = DB::connection()->getPdo();

        $stmtCek = $pdo->prepare(
            'SELECT COUNT(*) FROM kegiatan 
             WHERE (kode_kegiatan = :kode OR email_pic = :email) AND id <> :id'
        );
        $stmtCek->execute([
            'kode' => $valid['kode_kegiatan'],
            'email' => $valid['email_pic'],
            'id' => $id,
        ]);

        if ((int) $stmtCek->fetchColumn() > 0) {
            return back()->withInput()->with('gagal', 'Kode kegiatan atau Email PIC sudah digunakan kegiatan lain.');
        }

        $stmtUbah = $pdo->prepare(
            'UPDATE kegiatan 
             SET kode_kegiatan = :kode, nama_kegiatan = :nama, email_pic = :email, 
                 kuota = :kuota, jenis_kegiatan_id = :jenis_id
             WHERE id = :id'
        );
        $stmtUbah->execute([
            'kode' => $valid['kode_kegiatan'],
            'nama' => $valid['nama_kegiatan'],
            'email' => $valid['email_pic'],
            'kuota' => (int) $valid['kuota'],
            'jenis_id' => (int) $valid['jenis_kegiatan_id'],
            'id' => $id,
        ]);

        return redirect()->route('kegiatan.index')->with('berhasil', 'Data kegiatan berhasil diperbarui.');
    } catch (\Throwable $error) {
        report($error);
        return back()->withInput()->with('gagal', 'Data gagal diperbarui akibat kesalahan sistem.');
    }
})->name('kegiatan.update');

// 5. DELETE
Route::delete('/kegiatan/{id}', function (int $id) {
    try {
        $pdo = DB::connection()->getPdo();

        $stmtHapus = $pdo->prepare('DELETE FROM kegiatan WHERE id = :id');
        $stmtHapus->execute(['id' => $id]);

        if ($stmtHapus->rowCount() === 0) {
            return redirect()->route('kegiatan.index')->with('gagal', 'Data kegiatan tidak ditemukan.');
        }

        return redirect()->route('kegiatan.index')->with('berhasil', 'Data kegiatan berhasil dihapus.');
    } catch (\Throwable $error) {
        report($error);
        return redirect()->route('kegiatan.index')->with('gagal', 'Data gagal dihapus akibat kesalahan sistem.');
    }
})->name('kegiatan.destroy');