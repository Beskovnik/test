/**
 * Simple Lightbox
 * Pure JS Lightbox for images and videos with navigation.
 */
class SimpleLightbox {
    constructor() {
        this.init();
    }

    init() {
        this.createModal();
        this.bindEvents();
    }

    createModal() {
        if (document.getElementById('lightbox-modal')) return;

        const modal = document.createElement('div');
        modal.id = 'lightbox-modal';
        modal.className = 'lightbox-modal';
        modal.style.display = 'none';
        modal.innerHTML = `
            <div class="lightbox-overlay"></div>
            <div class="lightbox-content">
                <button class="lightbox-close" aria-label="Close">
                    <span class="material-icons">close</span>
                </button>
                <button class="lightbox-prev" aria-label="Previous">
                    <span class="material-icons">chevron_left</span>
                </button>
                <div class="lightbox-media-container"></div>
                <button class="lightbox-next" aria-label="Next">
                    <span class="material-icons">chevron_right</span>
                </button>
                <div class="lightbox-caption"></div>
            </div>
            <div class="lightbox-loader">
                <div class="spinner"></div>
            </div>
        `;
        document.body.appendChild(modal);

        // Add Styles dynamically if not present
        if (!document.getElementById('lightbox-styles')) {
            const style = document.createElement('style');
            style.id = 'lightbox-styles';
            style.textContent = `
                .lightbox-modal {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    z-index: 2000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    opacity: 0;
                    pointer-events: none;
                    transition: opacity 0.3s ease;
                }
                .lightbox-modal.open {
                    opacity: 1;
                    pointer-events: auto;
                }
                .lightbox-overlay {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.9);
                    backdrop-filter: blur(5px);
                }
                .lightbox-content {
                    position: relative;
                    z-index: 2001;
                    width: 100%;
                    height: 100%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .lightbox-media-container {
                    max-width: 100%;
                    max-height: 100%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .lightbox-media-container img, .lightbox-media-container video {
                    max-width: 90vw;
                    max-height: 90vh;
                    object-fit: contain;
                    box-shadow: 0 0 20px rgba(0,0,0,0.5);
                }
                .lightbox-close, .lightbox-prev, .lightbox-next {
                    position: absolute;
                    background: rgba(255, 255, 255, 0.1);
                    border: none;
                    color: white;
                    cursor: pointer;
                    padding: 10px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: background 0.2s;
                    z-index: 2002;
                }
                .lightbox-close:hover, .lightbox-prev:hover, .lightbox-next:hover {
                    background: rgba(255, 255, 255, 0.3);
                }
                .lightbox-close {
                    top: 20px;
                    right: 20px;
                }
                .lightbox-prev {
                    left: 20px;
                }
                .lightbox-next {
                    right: 20px;
                }
                .lightbox-caption {
                    position: absolute;
                    bottom: 20px;
                    left: 0;
                    width: 100%;
                    text-align: center;
                    color: rgba(255, 255, 255, 0.8);
                    pointer-events: none;
                }
                /* Info Panel */
                .lightbox-info-panel {
                    position: absolute;
                    right: 0;
                    top: 0;
                    width: 300px;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.85);
                    backdrop-filter: blur(10px);
                    padding: 20px;
                    border-left: 1px solid rgba(255,255,255,0.1);
                    transform: translateX(100%);
                    transition: transform 0.3s ease;
                    overflow-y: auto;
                    color: white;
                    z-index: 2005;
                }
                .lightbox-info-panel.visible {
                    transform: translateX(0);
                }
                .lightbox-info-panel h3 {
                    margin-top: 0;
                    font-size: 1.2rem;
                    border-bottom: 1px solid rgba(255,255,255,0.1);
                    padding-bottom: 10px;
                    margin-bottom: 15px;
                }
                .info-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 8px;
                    font-size: 0.9rem;
                }
                .info-label {
                    color: rgba(255,255,255,0.6);
                }
                .info-value {
                    text-align: right;
                    font-weight: 500;
                }
                .lightbox-info-toggle {
                    position: absolute;
                    top: 20px;
                    right: 60px; /* Left of close button */
                    background: rgba(255, 255, 255, 0.1);
                    border: none;
                    color: white;
                    cursor: pointer;
                    padding: 10px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: background 0.2s;
                    z-index: 2002;
                }
                .lightbox-info-toggle:hover {
                    background: rgba(255, 255, 255, 0.3);
                }

                .lightbox-loader {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    z-index: 1999;
                    display: none;
                }
                .lightbox-loader .spinner {
                    width: 40px;
                    height: 40px;
                    border: 4px solid rgba(255,255,255,0.1);
                    border-left-color: white;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                }
                @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
                @media (max-width: 900px) {
                    .lightbox-info-panel {
                        width: 100%;
                        height: 40%;
                        top: auto;
                        bottom: 0;
                        left: 0;
                        right: 0;
                        border-left: none;
                        border-top: 1px solid rgba(255,255,255,0.1);
                        transform: translateY(100%);
                    }
                    .lightbox-info-panel.visible {
                        transform: translateY(0);
                    }
                    .lightbox-media-container img, .lightbox-media-container video {
                         /* Make space for panel when visible? Or just overlay */
                    }
                }
                @media (max-width: 600px) {
                    .lightbox-prev, .lightbox-next { bottom: 20px; top: auto; }
                    .lightbox-prev { left: 20px; }
                    .lightbox-next { right: 20px; }
                    .lightbox-media-container img, .lightbox-media-container video {
                        max-width: 100vw;
                        max-height: 80vh;
                    }
                }
            `;
            document.head.appendChild(style);
        }

        this.modal = document.getElementById('lightbox-modal');
        this.container = this.modal.querySelector('.lightbox-media-container');
        this.loader = this.modal.querySelector('.lightbox-loader');

        // Add info elements if missing
        if (!this.modal.querySelector('.lightbox-info-panel')) {
            const panel = document.createElement('div');
            panel.className = 'lightbox-info-panel';
            panel.innerHTML = `
                <h3>Informacije o sliki</h3>
                <div class="info-content"></div>
            `;
            this.modal.querySelector('.lightbox-content').appendChild(panel);

            const toggle = document.createElement('button');
            toggle.className = 'lightbox-info-toggle';
            toggle.innerHTML = '<span class="material-icons">info</span>';
            toggle.ariaLabel = 'Toggle Info';
            this.modal.querySelector('.lightbox-content').appendChild(toggle);

            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                panel.classList.toggle('visible');
            });

            // Close panel when clicking outside? Overlay handles close all.
        }

        this.infoPanel = this.modal.querySelector('.lightbox-info-panel');
        this.infoContent = this.modal.querySelector('.info-content');
    }

    bindEvents() {
        // Delegate click for triggers
        document.body.addEventListener('click', (e) => {
            const trigger = e.target.closest('.lightbox-trigger');
            if (trigger) {
                e.preventDefault();
                this.open(trigger);
            }
        });

        // Close events
        this.modal.querySelector('.lightbox-close').addEventListener('click', () => this.close());
        this.modal.querySelector('.lightbox-overlay').addEventListener('click', () => this.close());

        // Navigation events
        this.modal.querySelector('.lightbox-prev').addEventListener('click', (e) => {
            e.stopPropagation();
            this.prev();
        });
        this.modal.querySelector('.lightbox-next').addEventListener('click', (e) => {
            e.stopPropagation();
            this.next();
        });

        // Keyboard
        document.addEventListener('keydown', (e) => {
            if (!this.isOpen) return;
            if (e.key === 'Escape') this.close();
            if (e.key === 'ArrowLeft') this.prev();
            if (e.key === 'ArrowRight') this.next();
        });
    }

    open(trigger) {
        this.currentTrigger = trigger;
        this.isOpen = true;
        this.modal.classList.add('open');
        this.loadMedia();
    }

    close() {
        this.isOpen = false;
        this.modal.classList.remove('open');
        this.container.innerHTML = '';
        // Stop any playing videos
    }

    loadMedia() {
        const trigger = this.currentTrigger;
        if (!trigger) return;

        const src = trigger.dataset.original;
        const type = trigger.dataset.type || 'image';
        const title = trigger.dataset.title || '';

        // Populate Info Panel
        this.updateInfoPanel(trigger.dataset);

        this.loader.style.display = 'block';
        this.container.innerHTML = ''; // Clear previous

        let mediaEl;

        if (type === 'video') {
            mediaEl = document.createElement('video');
            mediaEl.src = src;
            mediaEl.controls = true;
            mediaEl.autoplay = true;
            mediaEl.playsInline = true; // Important for iOS
            mediaEl.onloadeddata = () => {
                this.loader.style.display = 'none';
            };
            mediaEl.onerror = () => {
                this.loader.style.display = 'none';
                this.container.innerHTML = '<span style="color:white">Napaka pri nalaganju videa.</span>';
            };
        } else {
            mediaEl = document.createElement('img');
            mediaEl.src = src;
            mediaEl.alt = title;
            mediaEl.onload = () => {
                this.loader.style.display = 'none';
            };
            mediaEl.onerror = () => {
                this.loader.style.display = 'none';
                // Try fallback to standard view if original fails (though it shouldn't)
                this.container.innerHTML = '<span style="color:white">Napaka pri nalaganju slike.</span>';
            };
        }

        this.container.appendChild(mediaEl);

        // Update caption
        const caption = this.modal.querySelector('.lightbox-caption');
        caption.innerHTML = `<h3>${title}</h3>`;
    }

    getTriggers() {
        // Helper to get list of triggers in current grid order
        return Array.from(document.querySelectorAll('.lightbox-trigger'));
    }

    next() {
        const triggers = this.getTriggers();
        const currentIndex = triggers.indexOf(this.currentTrigger);
        if (currentIndex !== -1 && currentIndex < triggers.length - 1) {
            this.open(triggers[currentIndex + 1]);
        }
    }

    prev() {
        const triggers = this.getTriggers();
        const currentIndex = triggers.indexOf(this.currentTrigger);
        if (currentIndex > 0) {
            this.open(triggers[currentIndex - 1]);
        }
    }

    updateInfoPanel(data) {
        let html = '';

        // Basic Info
        if (data.filename) html += this.renderRow('Ime datoteke', data.filename);
        if (data.dims) html += this.renderRow('Dimenzije', data.dims);
        if (data.size) html += this.renderRow('Velikost', data.size);
        if (data.mime) html += this.renderRow('Tip', data.mime);

        // EXIF
        if (data.exif) {
            try {
                const exif = JSON.parse(data.exif);

                // Date Taken
                if (exif.taken_at) {
                    const date = new Date(exif.taken_at * 1000);
                    html += this.renderRow('Datum nastanka', date.toLocaleString('sl-SI'));
                }

                // Camera
                if (exif.make || exif.model) {
                    const cam = [exif.make, exif.model].filter(Boolean).join(' ');
                    html += this.renderRow('Kamera', cam);
                }

                // Lens
                if (exif.lens) html += this.renderRow('Objektiv', exif.lens);

                // Settings
                if (exif.iso) html += this.renderRow('ISO', exif.iso);
                if (exif.aperture) html += this.renderRow('Zaslonka', 'f/' + exif.aperture);
                if (exif.shutter) html += this.renderRow('Čas osvetlitve', exif.shutter);
                if (exif.focal) html += this.renderRow('Goriščnica', exif.focal + ' mm');

            } catch (e) {
                console.error("EXIF parse error", e);
            }
        }

        // Link to view page
        html += `<div style="margin-top:20px;text-align:center;"><a href="/view.php?id=${data.id}" class="button small">Poglej podrobno</a></div>`;

        this.infoContent.innerHTML = html;
    }

    renderRow(label, value) {
        return `
            <div class="info-row">
                <span class="info-label">${label}:</span>
                <span class="info-value">${value}</span>
            </div>
        `;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.lightbox = new SimpleLightbox();
});
