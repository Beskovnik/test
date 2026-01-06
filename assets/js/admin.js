
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
    // Add CSRF token from meta tag
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (csrfToken) {
        data.csrf_token = csrfToken;
    }

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

    if (res.message && !res.error) {
        showToast(res.message);
        if (row) row.remove();
    } else {
        if (row) row.style.opacity = '1';
        showToast(res.error || res.message || 'Napaka', 'error');
    }
}

async function toggleUser(id, btn) {
    const res = await apiCall('/admin/api/user.php', { action: 'toggle', id });
    if (res.message && !res.error) {
        showToast(res.message);
        // Update badge UI
        const row = btn.closest('tr');
        const badge = row.querySelector('.badge'); // Assuming badge is in the row
        // The badge that indicates status is the second badge usually, or checked by content
        // In our HTML: <td><span class="badge">Role</span></td> <td><span class="badge" style="...">Status</span></td>
        // We need the status badge.
        const badges = row.querySelectorAll('.badge');
        const statusBadge = badges[1]; // 2nd one

        if (statusBadge) {
            statusBadge.textContent = res.active ? 'Aktiven' : 'Blokiran';
            statusBadge.style.background = res.active ? 'green' : 'red';
        }
        btn.textContent = res.active ? 'Blokiraj' : 'Aktiviraj';
    } else {
        showToast(res.error || res.message, 'error');
    }
}

async function resetPassword(id) {
    if (!confirm('Ponastavim geslo uporabnika?')) return;
    const res = await apiCall('/admin/api/user.php', { action: 'reset', id });
    if (res.message && !res.error) {
        alert(res.message); // Alert is better here so admin sees the new pass
    } else {
        showToast(res.error || res.message, 'error');
    }
}

// Modal Logic
function openAddUserModal() {
    const el = document.getElementById('addUserModal');
    if (el) el.style.display = 'flex';
}

function closeAddUserModal() {
    const el = document.getElementById('addUserModal');
    if (el) el.style.display = 'none';
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

            if (res.message && !res.error) {
                showToast(res.message);
                closeAddUserModal();
                form.reset();
                setTimeout(() => location.reload(), 1000); // Reload to show new user
            } else {
                showToast(res.error || res.message, 'error');
            }
        });
    }
});
