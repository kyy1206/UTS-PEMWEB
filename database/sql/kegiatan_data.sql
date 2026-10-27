USE kegiatan_mahasiswa_2401020035;

INSERT INTO jenis_kegiatan (nama_jenis) VALUES
    ('Akademik'),
    ('Sosial');

INSERT INTO kegiatan 
    (kode_kegiatan, nama_kegiatan, email_pic, kuota, jenis_kegiatan_id)
VALUES
    ('KG00000001', 'Pelatihan Web', 'web@kampus.ac.id', 40, 1),
    ('KG00000002', 'Seminar Karier', 'karier@kampus.ac.id', 100, 1),
    ('KG00000003', 'Bakti Pesisir', 'pesisir@kampus.ac.id', 60, 2);