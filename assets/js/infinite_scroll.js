// Infinite Scroll Logic
document.addEventListener('DOMContentLoaded', () => {
    const sentinel = document.getElementById('scroll-sentinel');
    const loader = document.getElementById('feed-loader');

    if (!sentinel) return;

    let nextPage = parseInt(sentinel.dataset.nextPage);
    let hasMore = sentinel.dataset.hasMore === 'true';
    let loading = false;

    const observer = new IntersectionObserver(async (entries) => {
        if (entries[0].isIntersecting && hasMore && !loading) {
            loading = true;
            if (loader) loader.classList.remove('hidden');

            try {
                // Fetch next page content
                const url = new URL(window.location.href);
                url.searchParams.set('page', nextPage);

                const response = await fetch(url.toString());
                const text = await response.text();

                // Parse response to find grid items
                const parser = new DOMParser();
                const doc = parser.parseFromString(text, 'text/html');

                // Extract new time groups or append to existing
                const newGroups = doc.querySelectorAll('.time-group');

                if (newGroups.length > 0) {
                    newGroups.forEach(group => {
                        // Check if last group on page has same title (date)
                        const allGroups = document.querySelectorAll('.time-group');
                        const lastGroup = allGroups.length > 0 ? allGroups[allGroups.length - 1] : null;
                        const groupTitleEl = group.querySelector('h2');
                        const groupTitle = groupTitleEl ? groupTitleEl.textContent : null;

                        const lastGroupTitleEl = lastGroup ? lastGroup.querySelector('h2') : null;
                        const lastGroupTitle = lastGroupTitleEl ? lastGroupTitleEl.textContent : null;

                        if (lastGroup && groupTitle && lastGroupTitle === groupTitle) {
                            // Merge grids by moving elements (preserves events/state)
                            const sourceGrid = group.querySelector('.grid');
                            const targetGrid = lastGroup.querySelector('.grid');

                            while (sourceGrid.firstChild) {
                                targetGrid.appendChild(sourceGrid.firstChild);
                            }
                        } else {
                            // Append new group before pagination/sentinel
                             sentinel.parentNode.insertBefore(group, sentinel);
                        }
                    });

                    // Update state
                    nextPage++;
                    sentinel.dataset.nextPage = nextPage;

                    // Check if the fetched page has a "next page" indicator in its logic
                    // We can check if the fetched page had a sentinel with has-more=true
                    const newSentinel = doc.getElementById('scroll-sentinel');
                    if (newSentinel && newSentinel.dataset.hasMore === 'true') {
                        hasMore = true;
                    } else {
                        hasMore = false;
                        sentinel.remove();
                        const endMsg = document.createElement('div');
                        endMsg.className = 'no-more-posts';
                        endMsg.textContent = 'Ni več objav.';
                        if (loader) loader.parentNode.appendChild(endMsg);
                    }
                } else {
                    hasMore = false;
                    sentinel.remove();
                }

            } catch (err) {
                console.error('Failed to load more posts', err);
            } finally {
                loading = false;
                if (loader) loader.classList.add('hidden');
            }
        }
    }, {
        rootMargin: '200px'
    });

    observer.observe(sentinel);
});
