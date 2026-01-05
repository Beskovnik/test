
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);

    // Trigger animation
    requestAnimationFrame(() => toast.classList.add('show'));

    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

async function apiCall(url, data) {
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        return await res.json();
    } catch (e) {
        return { ok: false, message: 'Network error' };
    }
}

async function deleteItem(type, id, btn) {
    if (!confirm('Res izbrišem?')) return;

    const row = btn.closest('tr');
    if (row) row.style.opacity = '0.5';

    const res = await apiCall('/admin/api/delete.php', { type, id });

    if (res.ok) {
        showToast(res.message);
        if (row) row.remove();
    } else {
        if (row) row.style.opacity = '1';
        showToast(res.message, 'error');
    }
}

async function toggleUser(id, btn) {
    const res = await apiCall('/admin/api/user.php', { action: 'toggle', id });
    if (res.ok) {
        showToast(res.message);
        // Update badge UI
        const badge = btn.closest('tr').querySelector('.badge');
        if (badge) {
            badge.textContent = res.active ? 'Aktiven' : 'Blokiran';
            badge.className = `badge ${res.active ? 'badge-active' : 'badge-blocked'}`;
            // If we assume existing badges have style inline or class, we adapt:
            // The existing code uses style="background: green/red". Let's override style.
            badge.style.background = res.active ? 'green' : 'red';
        }
        btn.textContent = res.active ? 'Blokiraj' : 'Aktiviraj';
    } else {
        showToast(res.message, 'error');
    }
}

async function resetPassword(id) {
    if (!confirm('Ponastavim geslo uporabnika?')) return;
    const res = await apiCall('/admin/api/user.php', { action: 'reset', id });
    if (res.ok) {
        alert(res.message); // Alert is better here so admin sees the new pass
    } else {
        showToast(res.message, 'error');
    }
}

// Modal Logic
function openAddUserModal() {
    document.getElementById('addUserModal').classList.add('active');
}

function closeAddUserModal() {
    document.getElementById('addUserModal').classList.remove('active');
}

// Form Handler
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('addUserForm');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            data.action = 'add';
            // Handle checkbox for active (if present) or default to 1
            data.active = formData.get('active') === 'on';

            const res = await apiCall('/admin/api/user.php', data);

            if (res.ok) {
                showToast(res.message);
                closeAddUserModal();
                form.reset();
                setTimeout(() => location.reload(), 1000); // Reload to show new user
            } else {
                showToast(res.message, 'error');
            }
        });
    }
});
