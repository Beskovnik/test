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
                const container = document.querySelector('main.container') || document.body;

                // Insert new content before the sentinel
                // Note: This is a simplified approach. Ideally we'd merge same-day groups.
                // For now, we just append sections.

                // We need to find where to append. The sentinel is likely at the end.
                // Let's insert before the sentinel.

                if (newGroups.length > 0) {
                    newGroups.forEach(group => {
                        // Check if last group on page has same title (date)
                        const lastGroup = document.querySelector('.time-group:last-of-type');
                        const groupTitle = group.querySelector('h2').textContent;

                        if (lastGroup && lastGroup.querySelector('h2').textContent === groupTitle) {
                            // Merge grids
                            const grid = group.querySelector('.grid');
                            lastGroup.querySelector('.grid').innerHTML += grid.innerHTML;
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
