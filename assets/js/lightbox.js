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
}

document.addEventListener('DOMContentLoaded', () => {
    window.lightbox = new SimpleLightbox();
});
