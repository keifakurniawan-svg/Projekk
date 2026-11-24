/* Modul 3 : JavaScript - DOM, event listener, validasi form, interaktivitas */
document.addEventListener('DOMContentLoaded', function () {

  // 1. Mode terang/gelap
  const btnTema = document.getElementById('btnTema');
  function ikonTema() {
    if (!btnTema) return;
    const gelap = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    btnTema.innerHTML = gelap ? '<i class="bi bi-sun"></i>' : '<i class="bi bi-moon-stars"></i>';
  }
  ikonTema();
  if (btnTema) btnTema.addEventListener('click', function () {
    const baru = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-bs-theme', baru);
    localStorage.setItem('tema', baru);
    ikonTema();
  });

  // 2. Sidebar responsif
  const btnSidebar = document.getElementById('btnSidebar');
  if (btnSidebar) btnSidebar.addEventListener('click', function () {
    document.getElementById('sidebar').classList.toggle('open');
  });

  // 3. Jam digital
  const jam = document.getElementById('jam');
  if (jam) {
    const hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    const tampil = function () {
      const n = new Date();
      jam.textContent = hari[n.getDay()] + ', ' + n.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) +
                        ' \u2022 ' + n.toLocaleTimeString('id-ID', { hour12: false });
    };
    tampil(); setInterval(tampil, 1000);
  }

  // 4. Filter cepat tabel
  document.querySelectorAll('input[data-filter-table]').forEach(function (input) {
    input.addEventListener('input', function () {
      const kata = input.value.toLowerCase();
      document.querySelectorAll(input.dataset.filterTable + ' tbody tr').forEach(function (tr) {
        tr.style.display = tr.textContent.toLowerCase().includes(kata) ? '' : 'none';
      });
    });
  });

  // 5. Konfirmasi aksi berbahaya
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      if (!confirm(form.dataset.confirm)) ev.preventDefault();
    });
  });

  // 6. Validasi form sisi klien
  document.querySelectorAll('form.needs-validation').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      if (!form.checkValidity()) { ev.preventDefault(); ev.stopPropagation(); }
      form.classList.add('was-validated');
    });
  });

  // 7. Tampilkan password + indikator kekuatan + konfirmasi
  document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const input = document.querySelector(btn.dataset.togglePassword);
      const tampil = input.type === 'password';
      input.type = tampil ? 'text' : 'password';
      btn.innerHTML = tampil ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
    });
  });
  const pw = document.getElementById('password'), pw2 = document.getElementById('password2');
  const bar = document.getElementById('strengthBar'), label = document.getElementById('strengthLabel');
  if (pw && bar) pw.addEventListener('input', function () {
    const v = pw.value; let skor = 0;
    if (v.length >= 6) skor++;
    if (v.length >= 10) skor++;
    if (/[A-Z]/.test(v) && /[a-z]/.test(v)) skor++;
    if (/\d/.test(v) && /[^A-Za-z0-9]/.test(v)) skor++;
    const info = [['0%', '#dc2626', ''], ['25%', '#dc2626', 'Lemah'], ['50%', '#f59e0b', 'Cukup'],
                  ['75%', '#84cc16', 'Baik'], ['100%', '#16a34a', 'Kuat']][v ? Math.max(skor, 1) : 0];
    bar.style.width = info[0]; bar.style.background = info[1]; label.textContent = info[2];
  });
  if (pw && pw2) {
    const cek = function () { pw2.setCustomValidity(pw2.value && pw2.value !== pw.value ? 'Password tidak sama' : ''); };
    pw.addEventListener('input', cek); pw2.addEventListener('input', cek);
  }

  // 8. Pratinjau foto
  const inputFoto = document.getElementById('foto'), previewFoto = document.getElementById('previewFoto');
  if (inputFoto && previewFoto) inputFoto.addEventListener('change', function () {
    const f = inputFoto.files[0];
    if (!f) return;
    if (f.size > 2 * 1024 * 1024) { alert('Ukuran foto maksimal 2 MB.'); inputFoto.value = ''; return; }
    previewFoto.innerHTML = '<img class="avatar avatar-lg" src="' + URL.createObjectURL(f) + '" alt="Pratinjau">';
  });

  // 9. Alert hilang otomatis
  document.querySelectorAll('.auto-dismiss').forEach(function (el) {
    setTimeout(function () { bootstrap.Alert.getOrCreateInstance(el).close(); }, 5000);
  });

  // 10. DYNAMIC SHIFT SCHEDULING: warna sel + hitung cakupan staf per shift secara langsung
  const editor = document.getElementById('editorJadwal');
  if (editor) {
    const shifts = JSON.parse(editor.dataset.shifts);          // [{id,nama,warna,wajib}]
    const minimum = parseInt(editor.dataset.min, 10) || 0;
    const warnaById = {}; shifts.forEach(function (s) { warnaById[s.id] = s.warna; });
    const selects = editor.querySelectorAll('select.sel-shift');
    const jmlHari = editor.querySelectorAll('th[data-hari]').length;
    const barisCov = document.getElementById('barisCov');
    const ringkasan = document.getElementById('ringkasanCov');

    function warnai(sel) {
      const w = warnaById[sel.value];
      sel.style.borderColor = w || '';
      sel.style.color = w || '';
      sel.style.fontWeight = w ? '600' : '';
    }
    function hitung() {
      let kurangTotal = 0;
      for (let h = 0; h < jmlHari; h++) {
        const cnt = {}; shifts.forEach(function (s) { cnt[s.id] = 0; });
        editor.querySelectorAll('select[data-hari="' + h + '"]').forEach(function (sel) { if (sel.value) cnt[sel.value]++; });
        let html = '';
        shifts.forEach(function (s) {
          const kurang = s.wajib && minimum > 0 && cnt[s.id] < minimum;
          if (kurang) kurangTotal++;
          if (s.wajib || cnt[s.id] > 0)
            html += '<div class="' + (kurang ? 'kurang' : 'ok') + '">' + s.nama + ' ' + cnt[s.id] + (s.wajib && minimum ? '/' + minimum : '') + (kurang ? ' !' : '') + '</div>';
        });
        barisCov.children[h + 1].innerHTML = html;
      }
      ringkasan.className = 'badge ' + (kurangTotal ? 'text-bg-danger' : 'text-bg-success');
      ringkasan.textContent = kurangTotal ? kurangTotal + ' slot kekurangan staf' : 'Semua shift wajib terpenuhi';
    }
    selects.forEach(function (sel) {
      warnai(sel);
      sel.addEventListener('change', function () { warnai(sel); hitung(); });
    });
    hitung();

    // Isi cepat satu baris: tombol "isi minggu" pada setiap staf
    editor.querySelectorAll('select[data-isi-baris]').forEach(function (pilih) {
      pilih.addEventListener('change', function () {
        const baris = pilih.closest('tr');
        baris.querySelectorAll('select.sel-shift:not(:disabled)').forEach(function (sel) { sel.value = pilih.value; warnai(sel); });
        pilih.value = '__'; hitung();
      });
    });
  }

  // 11. TUKAR SHIFT: dropdown tujuan bergantung pada shift milik pengaju
  const pilihSaya = document.getElementById('jadwal_saya'), pilihTujuan = document.getElementById('jadwal_tujuan');
  if (pilihSaya && pilihTujuan) {
    const kandidat = JSON.parse(document.getElementById('dataKandidat').textContent);   // semua shift rekan sedivisi
    const info = document.getElementById('infoTukar');
    function isiTujuan() {
      const opt = pilihSaya.selectedOptions[0];
      pilihTujuan.innerHTML = '<option value="">-- pilih shift rekan --</option>';
      if (!opt || !opt.value) { pilihTujuan.disabled = true; return; }
      const idShiftSaya = opt.dataset.shift, tglSaya = opt.dataset.tanggal;
      const dipakaiSaya = (document.getElementById('dataTanggalSaya') ? JSON.parse(document.getElementById('dataTanggalSaya').textContent) : []);
      let n = 0;
      kandidat.forEach(function (k) {
        if (k.tanggal === tglSaya && k.id_shift === idShiftSaya) return;        // shift sama di hari yang sama tidak berguna
        if (k.tanggal !== tglSaya && dipakaiSaya.indexOf(k.tanggal) !== -1) return;  // saya sudah punya shift di tanggal itu
        const o = document.createElement('option');
        o.value = k.id_jadwal; o.textContent = k.label; pilihTujuan.appendChild(o); n++;
      });
      pilihTujuan.disabled = false;
      info.textContent = n ? n + ' shift rekan dapat ditukar.' : 'Tidak ada shift rekan yang cocok.';
    }
    pilihSaya.addEventListener('change', isiTujuan);
    isiTujuan();
  }

  // 12. HANDOVER: hitung karakter + tombol kirim aktif jika checklist lengkap
  const catatan = document.getElementById('catatan'), hitungCatatan = document.getElementById('hitungCatatan');
  if (catatan && hitungCatatan) {
    const perbarui = function () { hitungCatatan.textContent = catatan.value.trim().length + ' / min. 20 karakter'; };
    catatan.addEventListener('input', perbarui); perbarui();
  }
  const cekItems = document.querySelectorAll('input.cek-tutup'), btnKirim = document.getElementById('btnKirimHandover');
  if (cekItems.length && btnKirim) {
    const periksa = function () { btnKirim.disabled = !Array.prototype.every.call(cekItems, function (c) { return c.checked; }); };
    cekItems.forEach(function (c) { c.addEventListener('change', periksa); }); periksa();
  }

  // 13. Hitung mundur shift hari ini
  const cd = document.getElementById('countdown');
  if (cd) {
    const mulai = new Date(cd.dataset.mulai.replace(' ', 'T')), selesai = new Date(cd.dataset.selesai.replace(' ', 'T'));
    const fmt = function (ms) { const m = Math.max(0, Math.floor(ms / 60000)); return Math.floor(m / 60) + ' jam ' + (m % 60) + ' menit'; };
    const t = function () {
      const n = new Date();
      if (n < mulai) cd.textContent = 'Shift dimulai dalam ' + fmt(mulai - n);
      else if (n < selesai) cd.textContent = 'Shift berlangsung, berakhir dalam ' + fmt(selesai - n);
      else cd.textContent = 'Shift telah berakhir';
    };
    t(); setInterval(t, 30000);
  }
});
