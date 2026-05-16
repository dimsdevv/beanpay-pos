    </main>
</div> <!-- End of Main Content Area -->

<!-- Flash Message Handler -->
<?php if(isset($_SESSION['success'])): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: '<?= addslashes($_SESSION['success']) ?>',
        confirmButtonColor: '#004ac6'
    });
</script>
<?php unset($_SESSION['success']); endif; ?>

<?php if(isset($_SESSION['error'])): ?>
<script>
    Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: '<?= addslashes($_SESSION['error']) ?>',
        confirmButtonColor: '#ba1a1a'
    });
</script>
<?php unset($_SESSION['error']); endif; ?>

<!-- Global Notification Center Component -->
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('notifCenter', () => ({
        open: false,
        items: [],
        unreadCount: 0,
        lastId: parseInt(localStorage.getItem('beanpay_last_notif_id') || '0'),
        pollTimer: null,
        isFirstLoad: true,
        userRole: '<?= $user["role"] ?? "" ?>',
        baseUrl: '<?= BASE_URL ?>',

        formatRp(num) {
            return 'Rp ' + parseInt(num || 0).toLocaleString('id-ID');
        },

        async fetchNotifications() {
            try {
                // Determine API endpoint based on role
                let endpoint = '';
                if (this.userRole === 'waiter') {
                    endpoint = `${this.baseUrl}/api/notif_waiter.php?last_id=${this.lastId}`;
                } else if (this.userRole === 'admin' || this.userRole === 'kasir') {
                    endpoint = `${this.baseUrl}/api/realtime.php?last_id=${this.lastId}`;
                } else {
                    return; // No notifs for dapur via this system
                }

                const res = await fetch(endpoint);
                if (!res.ok) return;
                const data = await res.json();

                if (this.userRole === 'waiter') {
                    this.handleWaiterNotifs(data);
                } else {
                    this.handleAdminNotifs(data);
                }
                this.isFirstLoad = false;
            } catch (err) {
                console.warn('[BeanPay Notif] Error:', err);
            }
        },

        handleWaiterNotifs(data) {
            if (data.notifs && data.notifs.length > 0) {
                const newItems = data.notifs.map(n => ({
                    id: n.id,
                    title: 'Pesanan Selesai!',
                    sub: n.pesan,
                    waktu: new Date(n.created_at).toLocaleTimeString('id-ID', {hour: '2-digit', minute:'2-digit'}),
                    isNew: true
                }));
                this.items = [...newItems, ...this.items].slice(0, 20);
                this.unreadCount = data.unread_count;

                if (!this.isFirstLoad) {
                    newItems.forEach(n => {
                        this.showToast('success', '🔔 ' + n.title, n.sub);
                    });
                }
                this.lastId = data.max_id;
                localStorage.setItem('beanpay_last_notif_id', String(data.max_id));
            }
        },

        handleAdminNotifs(data) {
            if (data.notifications && data.notifications.length > 0) {
                const newItems = data.notifications.map(n => ({
                    id: n.id,
                    title: '💰 Pembayaran Baru',
                    sub: `${n.nomor_pesanan} • <b>${this.formatRp(n.total)}</b> (${n.metode})`,
                    waktu: 'Baru saja',
                    isNew: true
                }));
                this.items = [...newItems, ...this.items].slice(0, 20);
                this.unreadCount += newItems.length;

                if (!this.isFirstLoad) {
                    newItems.forEach(n => {
                        this.showToast('success', n.title, n.sub);
                    });
                }
                this.lastId = data.last_id;
                localStorage.setItem('beanpay_last_notif_id', String(data.last_id));
            } else if (data.last_id > this.lastId) {
                this.lastId = data.last_id;
                localStorage.setItem('beanpay_last_notif_id', String(data.last_id));
            }

            if (this.isFirstLoad && data.activity && data.activity.length > 0 && this.items.length === 0) {
                this.items = data.activity.map(a => ({
                    id: a.id,
                    title: 'Pembayaran ' + a.nomor_pesanan,
                    sub: `${this.formatRp(a.total)} • ${a.metode} • ${a.kasir}`,
                    waktu: a.waktu,
                    isNew: false
                }));
            }
        },

        showToast(icon, title, html) {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 5000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.onmouseenter = Swal.stopTimer;
                    toast.onmouseleave = Swal.resumeTimer;
                }
            });
            Toast.fire({
                icon: icon,
                title: `<span style="font-weight:700;font-size:14px">${title}</span>`,
                html: `<span style="font-size:13px">${html}</span>`
            });
        },

        async markAllRead() {
            if (this.userRole === 'waiter') {
                const fd = new FormData();
                fd.append('action', 'mark_all_read');
                await fetch(`${this.baseUrl}/api/notif_waiter.php`, { method: 'POST', body: fd });
            }
            this.unreadCount = 0;
            this.items = this.items.map(i => ({ ...i, isNew: false }));
        },

        startPolling() {
            this.fetchNotifications();
            this.pollTimer = setInterval(() => this.fetchNotifications(), 15000);
        },

        destroy() {
            if (this.pollTimer) clearInterval(this.pollTimer);
        }
    }));
});
</script>

</body>
</html>

