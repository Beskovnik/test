
function showToast(message, type = 'success') {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:10px;pointer-events:none;';
        document.body.appendChild(toastContainer);
    }

    const el = document.createElement('div');
    const isError = type === 'error';
    const borderColor = isError ? 'var(--danger, #e74c3c)' : 'var(--success, #2ecc71)';

    el.className = 'toast';
    el.style.cssText = `
        padding: 1rem 1.5rem;
        border-radius: 1rem;
        background: var(--panel, #2a2a2a);
        backdrop-filter: blur(16px);
        border: 1px solid var(--border, #444);
        color: #fff;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        border-left: 4px solid ${borderColor};
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        pointer-events: auto;
        opacity: 0;
        transform: translateY(-10px);
        transition: opacity 0.3s ease, transform 0.3s ease;
    `;

    el.innerHTML = `<span style="font-weight:600;">${message}</span>`;
    toastContainer.appendChild(el);

    // Animate In
    requestAnimationFrame(() => {
        el.style.opacity = '1';
        el.style.transform = 'translateY(0)';
    });

    // Remove after 3s
    setTimeout(() => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(-10px)';
        setTimeout(() => el.remove(), 300);
    }, 3000);
}

// Global Copy Helper
window.copyToClipboard = async function(text) {
    try {
        await navigator.clipboard.writeText(text);
        showToast('Kopirano v odložišče!');
    } catch (err) {
        // Fallback
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            showToast('Kopirano (fallback)!');
        } catch (err) {
            prompt('Kopiraj povezavo:', text);
        }
        document.body.removeChild(textarea);
    }
};

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
        // Simple heuristic: find badge with Aktiven/Blokiran text or color
        let statusBadge = null;
        badges.forEach(b => {
            if(b.innerText.includes('Aktiven') || b.innerText.includes('Blokiran')) statusBadge = b;
        });
        if (!statusBadge && badges.length > 1) statusBadge = badges[1];

        if (statusBadge) {
            statusBadge.textContent = res.active ? 'Aktiven' : 'Blokiran';
            statusBadge.style.background = res.active ? 'rgba(46, 204, 113, 0.2)' : 'rgba(231, 76, 60, 0.2)';
            statusBadge.style.color = res.active ? '#2ecc71' : '#e74c3c';
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
