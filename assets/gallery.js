/**
 * Gallery Controller
 * Handles Multi-select, Bulk Actions, and Grid Interactions.
 */

class GalleryController {
    constructor() {
        this.selectedIds = new Set();
        this.isSelectMode = false;

        // DOM Elements
        this.toolbar = document.getElementById('gallery-toolbar');
        this.toggleBtn = document.getElementById('toggle-select-mode');
        this.cancelBtn = document.getElementById('cancel-selection');
        this.countBadge = document.getElementById('selected-count');
        this.shareBtn = document.getElementById('bulk-share-btn');
        this.container = document.querySelector('.main'); // or document.body

        // Bindings
        this.initListeners();
    }

    initListeners() {
        // Toggle Button
        if (this.toggleBtn) {
            this.toggleBtn.addEventListener('click', () => this.toggleSelectMode(true));
        }

        // Cancel Button
        if (this.cancelBtn) {
            this.cancelBtn.addEventListener('click', () => this.toggleSelectMode(false));
        }

        // Share Button
        if (this.shareBtn) {
            this.shareBtn.addEventListener('click', () => this.handleBulkShare());
        }

        // Keyboard (ESC)
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isSelectMode) {
                this.toggleSelectMode(false);
            }
        });

        // Delegate Card Clicks
        document.addEventListener('click', (e) => {
            const card = e.target.closest('.gallery-card');
            if (!card) return;

            if (this.isSelectMode) {
                e.preventDefault(); // Prevent navigation
                e.stopPropagation();
                this.toggleSelection(card);
            }
        });
    }

    toggleSelectMode(active) {
        this.isSelectMode = active;
        if (active) {
            this.toolbar.classList.add('active');
            document.body.classList.add('select-mode');
        } else {
            this.toolbar.classList.remove('active');
            document.body.classList.remove('select-mode');
            this.clearSelection();
        }
    }

    toggleSelection(card) {
        const id = card.dataset.id;
        if (!id) return;

        if (this.selectedIds.has(id)) {
            this.selectedIds.delete(id);
            card.classList.remove('selected');
        } else {
            this.selectedIds.add(id);
            card.classList.add('selected');
        }
        this.updateCounter();
    }

    clearSelection() {
        this.selectedIds.clear();
        document.querySelectorAll('.gallery-card.selected').forEach(el => el.classList.remove('selected'));
        this.updateCounter();
    }

    updateCounter() {
        if (this.countBadge) {
            this.countBadge.textContent = `Izbrano: ${this.selectedIds.size}`;
        }
    }

    async handleBulkShare() {
        if (this.selectedIds.size === 0) return;

        const ids = Array.from(this.selectedIds).map(id => parseInt(id, 10));

        // Show loading state
        const originalText = this.shareBtn.innerHTML;
        this.shareBtn.disabled = true;
        this.shareBtn.innerHTML = '<span class="material-icons spin">refresh</span> Generiram...';

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

            const response = await fetch('/api/share/create.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    media_ids: ids,
                    // Optional: title could be added here if we had a prompt
                })
            });

            const data = await response.json();

            if (data.success) {
                this.showSingleLinkModal(data.share_url);
            } else {
                alert('Napaka: ' + (data.error || 'Neznana napaka'));
            }

        } catch (err) {
            console.error(err);
            alert('Napaka pri povezavi s strežnikom.');
        } finally {
            this.shareBtn.disabled = false;
            this.shareBtn.innerHTML = originalText;
        }
    }

    showSingleLinkModal(url) {
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop';

        const modal = document.createElement('div');
        modal.className = 'bulk-share-results'; // Reuse class for styling

        // Ensure absolute URL if relative
        const fullUrl = url.startsWith('http') ? url : window.location.origin + url;

        const html = `
            <div class="bulk-share-header">
                <h3 style="margin:0">Zbirka ustvarjena</h3>
                <button class="button icon-only close-modal" style="background:none;border:none;color:white;cursor:pointer;">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <div class="bulk-share-body">
                <p style="color:var(--muted);margin-bottom:1rem;">Uspešno ste ustvarili povezavo do ${this.selectedIds.size} izbranih elementov.</p>

                <div class="share-link-row" style="background: rgba(255,255,255,0.1); padding: 1rem;">
                    <input type="text" readonly value="${fullUrl}" class="share-link-input" style="font-size:1.1rem; width:100%;" onclick="this.select()">
                </div>
            </div>
            <div class="bulk-share-footer">
                <button class="button btn-secondary copy-main-btn" style="background:var(--accent);color:white;">Kopiraj Povezavo</button>
                <button class="button close-modal">Zapri</button>
            </div>
        `;

        modal.innerHTML = html;
        document.body.appendChild(backdrop);
        document.body.appendChild(modal);

        // Bind Modal Events
        const close = () => {
            backdrop.remove();
            modal.remove();
        };

        modal.querySelectorAll('.close-modal').forEach(b => b.onclick = close);
        backdrop.onclick = close;

        const copyBtn = modal.querySelector('.copy-main-btn');
        if (copyBtn) {
            copyBtn.onclick = () => {
                navigator.clipboard.writeText(fullUrl);
                const original = copyBtn.textContent;
                copyBtn.textContent = 'Kopirano!';
                setTimeout(() => copyBtn.textContent = original, 2000);
            };
        }
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    window.galleryController = new GalleryController();
});
