/* ============================================================
   assets/js/app.js  —  Damat Application Logic (FIXED)
   ============================================================ */

   'use strict';

   // ============================================================
   // UTILS
   // ============================================================
   const fmt = (n) =>
     'Rp ' + Number(n).toLocaleString('id-ID', { minimumFractionDigits: 0 });
   
   function toast(msg, type = 'default', duration = 3500) {
     const container = document.getElementById('toast-container');
     if (!container) return;
     const el = document.createElement('div');
     el.className = `toast ${type}`;
     el.textContent = msg;
     container.appendChild(el);
     setTimeout(() => {
       el.style.opacity = '0';
       el.style.transform = 'translateX(20px)';
       el.style.transition = 'all .3s';
       setTimeout(() => el.remove(), 300);
     }, duration);
   }
   
   // ============================================================
   // SIDEBAR MOBILE TOGGLE
   // ============================================================
   (function initSidebar() {
     const hamburger = document.querySelector('.hamburger');
     const sidebar   = document.querySelector('.sidebar');
     const overlay   = document.getElementById('sidebar-overlay');
   
     if (!hamburger || !sidebar) return;
   
     hamburger.addEventListener('click', () => {
       sidebar.classList.add('open');
       if (overlay) overlay.style.display = 'block';
     });
   
     if (overlay) {
       overlay.addEventListener('click', () => {
         sidebar.classList.remove('open');
         overlay.style.display = 'none';
       });
     }
   })();
   
   // ============================================================
   // IMPULSE WARNING LOGIC
   // ============================================================
   const ImpulseWarning = (() => {
     const THRESHOLD = 0.80; // 80% anggaran
   
     function check({ expense, budget, amount, onProceed, onCancel }) {
       // Jika tidak ada anggaran, langsung lanjut
       if (!budget || budget <= 0) return onProceed();
   
       const projectedTotal   = expense + amount;
       const projectedPercent = projectedTotal / budget;
   
       // Jika di bawah threshold, langsung lanjut
       if (projectedPercent < THRESHOLD) return onProceed();
   
       const overlay = document.getElementById('impulse-modal');
       if (!overlay) return onProceed();
   
       const pct = Math.round(projectedPercent * 100);
       const rem = budget - expense;
   
       // FIX: Menggunakan optional chaining agar tidak error jika elemen tidak ada
       if (overlay.querySelector('.impulse-pct')) 
           overlay.querySelector('.impulse-pct').textContent = `${pct}%`;
       
       if (overlay.querySelector('.impulse-remaining'))
           overlay.querySelector('.impulse-remaining').textContent = fmt(rem);
   
       overlay.classList.add('open');
   
       const btnProceed = overlay.querySelector('#impulse-proceed');
       const btnCancel  = overlay.querySelector('#impulse-cancel');
   
       const cleanup = () => { overlay.classList.remove('open'); };
   
       btnProceed.onclick = () => { cleanup(); onProceed(); };
       btnCancel.onclick  = () => { cleanup(); if (onCancel) onCancel(); };
     }
   
     return { check };
   })();
   
   // ============================================================
   // TRANSACTION FORM
   // ============================================================
   (function initTxForm() {
     const form = document.getElementById('tx-form');
     if (!form) return;
   
     const typeInBtn = document.getElementById('type-income');
     const typeExBtn = document.getElementById('type-expense');
     const typeInput = document.getElementById('tx-type');
     const catSel    = document.getElementById('tx-category');
     const amountInp = document.getElementById('tx-amount');
   
     const CATEGORIES = {
       income:  ['Gaji', 'Freelance', 'Bonus', 'Investasi', 'Hadiah', 'Lainnya'],
       expense: ['Makanan', 'Transportasi', 'Belanja', 'Tagihan', 'Kesehatan', 'Hiburan', 'Pendidikan', 'Pakaian', 'Lainnya'],
     };
   
     function setType(type) {
       typeInput.value = type;
       typeInBtn.className = 'type-btn' + (type === 'income'  ? ' active-income'  : '');
       typeExBtn.className = 'type-btn' + (type === 'expense' ? ' active-expense' : '');
   
       catSel.innerHTML = CATEGORIES[type]
         .map(c => `<option value="${c}">${c}</option>`).join('');
     }
   
     // Init default state
     setType(typeInput.value || 'expense');
   
     typeInBtn.addEventListener('click', () => setType('income'));
     typeExBtn.addEventListener('click', () => setType('expense'));
   
     // Format amount: hanya angka
     amountInp.addEventListener('input', function () {
       this.value = this.value.replace(/[^0-9]/g, '');
     });
   
     // Submit handler
     form.addEventListener('submit', function (e) {
       const type   = typeInput.value;
       const amount = parseFloat(amountInp.value) || 0;
   
       // Impulse check hanya untuk pengeluaran (expense)
       if (type === 'expense') {
         e.preventDefault(); // Stop form dulu untuk cek impulse
   
         const budget  = parseFloat(form.dataset.budget  || 0);
         const expense = parseFloat(form.dataset.expense || 0);
   
         ImpulseWarning.check({
           expense,
           budget,
           amount,
           onProceed: () => {
             // Kirim form secara manual setelah disetujui di modal
             form.submit();
           },
           onCancel: () => {
             toast('Transaksi dibatalkan. Tetap semangat hemat! 💪', 'default');
           },
         });
       }
       // Jika pemasukan, form akan terkirim secara otomatis (native)
     });
   })();
   
   // ============================================================
   // CHARTS (Dashboard)
   // ============================================================
   function initLineChart(labels, incomeData, expenseData) {
     const canvas = document.getElementById('line-chart');
     if (!canvas || typeof Chart === 'undefined') return;
   
     const ctx = canvas.getContext('2d');
     const gradExp = ctx.createLinearGradient(0, 0, 0, 220);
     gradExp.addColorStop(0, 'rgba(196,117,90,.25)');
     gradExp.addColorStop(1, 'rgba(196,117,90,0)');
   
     new Chart(ctx, {
       type: 'line',
       data: {
         labels,
         datasets: [
           {
             label: 'Pengeluaran',
             data: expenseData,
             borderColor: '#C4755A',
             backgroundColor: gradExp,
             borderWidth: 2.5,
             tension: .4,
             fill: true,
             pointBackgroundColor: '#C4755A',
           },
           {
             label: 'Pemasukan',
             data: incomeData,
             borderColor: '#5C8A5C',
             borderWidth: 2.5,
             tension: .4,
           }
         ],
       },
       options: {
           responsive: true,
           plugins: {
               legend: { display: true },
               tooltip: {
                   callbacks: {
                       label: (ctx) => ` ${ctx.dataset.label}: ${fmt(ctx.parsed.y)}`,
                   }
               }
           }
       }
     });
   }
   
   function initDoughnutChart(labels, data) {
     const canvas = document.getElementById('donut-chart');
     if (!canvas || typeof Chart === 'undefined') return;
   
     const COLORS = ['#BC9870','#8B6340','#C4755A','#7D9B76','#D4BC9A','#523A22','#B8D4B3'];
   
     new Chart(canvas.getContext('2d'), {
       type: 'doughnut',
       data: {
         labels,
         datasets: [{
           data,
           backgroundColor: COLORS,
         }],
       },
       options: {
         cutout: '70%',
         plugins: {
           tooltip: {
             callbacks: {
               label: (ctx) => ` ${ctx.label}: ${fmt(ctx.parsed)}`,
             }
           }
         }
       }
     });
   }
   
   // ============================================================
   // OTHER UI LOGIC
   // ============================================================
   (function initFilterChips() {
     const chips = document.querySelectorAll('.filter-chip');
     chips.forEach(chip => {
       chip.addEventListener('click', function () {
         chips.forEach(c => c.classList.remove('active'));
         this.classList.add('active');
         const f = this.dataset.filter;
   
         document.querySelectorAll('.tx-card').forEach(card => {
           card.style.display = (!f || f === 'all' || card.dataset.type === f) ? '' : 'none';
         });
       });
     });
   })();
   
   function confirmDelete(id) {
     if (confirm('Hapus transaksi ini?')) {
       const f = document.createElement('form');
       f.method = 'POST';
       f.innerHTML = `<input type="hidden" name="action" value="delete_tx"><input type="hidden" name="tx_id" value="${id}">`;
       document.body.appendChild(f);
       f.submit();
     }
   }
   
// Kode pendaftaran Service Worker (Ditaruh di dalam app.js)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        // Alamat file sw.js tetap mengarah ke folder utama luar (root)
        navigator.serviceWorker.register('/sw.js') 
            .then(reg => console.log('Service Worker Registered!', reg))
            .catch(err => console.log('Service Worker Gagal:', err));
    });
}