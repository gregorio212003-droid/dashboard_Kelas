
document.addEventListener('DOMContentLoaded', function () {

    const bellButton = document.getElementById('notificationBell');
    const dropdown = document.getElementById('notificationDropdown');
    const badge = document.getElementById('notificationBadge');
    const list = document.getElementById('notificationList');
    const loading = document.getElementById('notificationLoading');

    if (!bellButton || !dropdown || !badge || !list) {
        return;
    }

    const apiUrl = 'notifikasi_api.php';

    // =================================
    // FORMAT WAKTU
    // =================================

    function escapeHTML(value) {

        return String(value ?? '').replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });

    }

    // =================================
    // MEMUAT NOTIFIKASI
    // =================================

    async function loadNotifications() {

        try {

            const response = await fetch(apiUrl, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('Gagal mengambil data.');
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Gagal mengambil notifikasi.');
            }

            // Update angka notifikasi

            const count = Number(data.unread_count) || 0;

            if (count > 0) {

                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'flex';

            } else {

                badge.textContent = '0';
                badge.style.display = 'none';

            }

            // Tampilkan daftar notifikasi

            renderNotifications(data.notifications || []);

        } catch (error) {

            console.error('Notifikasi:', error);

        } finally {

            if (loading) {
                loading.style.display = 'none';
            }

        }

    }

    // =================================
    // MENAMPILKAN DAFTAR
    // =================================

    function renderNotifications(notifications) {

        list.innerHTML = '';

        if (notifications.length === 0) {

            const empty = document.createElement('div');

            empty.className = 'notification-empty';

            empty.textContent = 'Tidak ada notifikasi baru.';

            list.appendChild(empty);

            return;

        }

        notifications.forEach(function (item) {

            const link = document.createElement('a');

            link.className = 'notification-item';

            link.href = 'pengumuman.php?id=' + encodeURIComponent(item.id);

            const icon = document.createElement('div');

            icon.className = 'notification-item-icon';
            icon.textContent = '🔔';

            const content = document.createElement('div');

            content.className = 'notification-item-content';

            const title = document.createElement('strong');

            title.textContent = item.title;

            const description = document.createElement('p');

            description.textContent = item.content;

            const time = document.createElement('small');

            time.textContent = item.created_at;

            content.appendChild(title);
            content.appendChild(description);
            content.appendChild(time);

            link.appendChild(icon);
            link.appendChild(content);

            list.appendChild(link);

        });

    }

    // =================================
    // BUKA / TUTUP DROPDOWN
    // =================================

    bellButton.addEventListener('click', function (event) {

        event.stopPropagation();

        const isOpen = dropdown.classList.toggle('show');

        bellButton.setAttribute(
            'aria-expanded',
            isOpen ? 'true' : 'false'
        );

        if (isOpen) {
            loadNotifications();
        }

    });

    // Tutup jika klik di luar dropdown

    document.addEventListener('click', function (event) {

        if (
            !dropdown.contains(event.target) &&
            !bellButton.contains(event.target)
        ) {

            dropdown.classList.remove('show');

            bellButton.setAttribute('aria-expanded', 'false');

        }

    });

    // Mencegah dropdown tertutup ketika diklik

    dropdown.addEventListener('click', function (event) {

        event.stopPropagation();

    });

    // =================================
    // UPDATE OTOMATIS SETIAP 15 DETIK
    // =================================

    loadNotifications();

    setInterval(function () {

        if (!document.hidden) {
            loadNotifications();
        }

    }, 15000);

    // Update kembali ketika tab dibuka

    document.addEventListener('visibilitychange', function () {

        if (!document.hidden) {
            loadNotifications();
        }

    });

});
