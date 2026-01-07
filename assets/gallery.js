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
        // Toggle Button (Inject this into the top actions or use the one in toolbar)
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

        const ids = Array.from(this.selectedIds);
        const results = [];

        // Show loading state
        const originalText = this.shareBtn.innerHTML;
        this.shareBtn.disabled = true;
        this.shareBtn.innerHTML = '<span class="material-icons spin">refresh</span> Generiram...';

        try {
            // Process in batches of 5 to avoid browser/server limits
            const batchSize = 5;
            for (let i = 0; i < ids.length; i += batchSize) {
                const batch = ids.slice(i, i + batchSize);
                const promises = batch.map(id => this.generateLink(id));
                const batchResults = await Promise.all(promises);
                results.push(...batchResults);
            }

            this.showResultsModal(results);

            // Mark items as public in UI
            results.forEach(res => {
                if (res.success) {
                    const card = document.querySelector(`.gallery-card[data-id="${res.id}"]`);
                    if (card) {
                        // Check if badge exists, if not create
                        let badges = card.querySelector('.card-badges');
                        if (!badges) {
                            badges = document.createElement('div');
                            badges.className = 'card-badges';
                            card.querySelector('.card-image-wrapper').appendChild(badges);
                        }
                        if (!card.querySelector('.card-badge.public')) {
                            const badge = document.createElement('span');
                            badge.className = 'card-badge public';
                            badge.innerHTML = '<span class="material-icons" style="font-size:10px">public</span> Javno';
                            badges.appendChild(badge);
                        }
                    }
                }
            });

            // Exit select mode? Maybe keep it to allow copying.
            // The prompt says: "Po deljenju naj se te slike v UI označijo ... Po uspehu pokaži modal"
            // We'll keep select mode active underneath the modal.

        } catch (err) {
            console.error(err);
            alert('Napaka pri generiranju povezav.');
        } finally {
            this.shareBtn.disabled = false;
            this.shareBtn.innerHTML = originalText;
        }
    }

    async generateLink(id) {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            const res = await fetch('/api/media/generate_public_link.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ id: id })
            });
            const data = await res.json();
            return { id, ...data };
        } catch (e) {
            return { id, success: false, error: e.message };
        }
    }

    showResultsModal(results) {
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop';

        const modal = document.createElement('div');
        modal.className = 'bulk-share-results';

        const successCount = results.filter(r => r.success).length;

        let html = `
            <div class="bulk-share-header">
                <h3 style="margin:0">Javne Povezave (${successCount}/${results.length})</h3>
                <button class="button icon-only close-modal" style="background:none;border:none;color:white;cursor:pointer;">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <div class="bulk-share-body">
        `;

        results.forEach(res => {
            if (res.success) {
                html += `
                    <div class="share-link-row">
                        <span style="font-weight:bold;color:var(--accent)">#${res.id}</span>
                        <input type="text" readonly value="${res.public_url}" class="share-link-input" onclick="this.select()">
                        <button class="button small copy-btn" data-url="${res.public_url}">Kopiraj</button>
                    </div>
                `;
            } else {
                html += `
                    <div class="share-link-row" style="border-left: 2px solid red;">
                        <span style="font-weight:bold;">#${res.id}</span>
                        <span style="color:red; font-size:0.9rem;">Napaka: ${res.error || 'Neznano'}</span>
                    </div>
                `;
            }
        });

        html += `
            </div>
            <div class="bulk-share-footer">
                <button class="button btn-secondary copy-all-btn">Kopiraj Vse URL-je</button>
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

        modal.querySelectorAll('.copy-btn').forEach(btn => {
            btn.onclick = () => {
                navigator.clipboard.writeText(btn.dataset.url);
                const original = btn.textContent;
                btn.textContent = 'Kopirano!';
                setTimeout(() => btn.textContent = original, 2000);
            };
        });

        modal.querySelector('.copy-all-btn').onclick = () => {
            const allUrls = results.filter(r => r.success).map(r => r.public_url).join('\n');
            navigator.clipboard.writeText(allUrls);
            const btn = modal.querySelector('.copy-all-btn');
            const original = btn.textContent;
            btn.textContent = 'Kopirano vse!';
            setTimeout(() => btn.textContent = original, 2000);
        };
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    window.galleryController = new GalleryController();
});
