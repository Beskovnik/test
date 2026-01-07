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
                // Optimization: Request only partial HTML (no header/footer) to reduce bandwidth and parsing time
                url.searchParams.set('partial', '1');

                const response = await fetch(url.toString());
                const text = await response.text();

                // Optimization: Use temporary container instead of DOMParser for faster fragment creation
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = text;

                // Extract new time groups or append to existing
                const newGroups = tempDiv.querySelectorAll('.time-group');

                // Use DocumentFragment for batch insertion to minimize reflows
                const fragment = document.createDocumentFragment();
                let lastGroup = document.querySelector('.time-group:last-of-type');
                // Optimization: Cache last group title to avoid repeated DOM reads
                let lastGroupTitle = lastGroup ? (lastGroup.querySelector('h2')?.textContent.trim() || null) : null;

                if (newGroups.length > 0) {
                    newGroups.forEach(group => {
                        // Check if last group on page has same title (date)
                        const groupTitleEl = group.querySelector('h2');
                        const groupTitle = groupTitleEl ? groupTitleEl.textContent.trim() : null;

                        if (lastGroup && groupTitle && lastGroupTitle === groupTitle) {
                            // Merge same-day groups
                            const sourceGrid = group.querySelector('.grid');
                            const targetGrid = lastGroup.querySelector('.grid');

                            if (sourceGrid && targetGrid) {
                                // Move all children to document fragment first if we were appending to DOM,
                                // but here we are appending to existing DOM node.
                                // We can use a fragment for the items to append them all at once.
                                const itemsFragment = document.createDocumentFragment();
                                while (sourceGrid.firstChild) {
                                    itemsFragment.appendChild(sourceGrid.firstChild);
                                }
                                targetGrid.appendChild(itemsFragment);
                            }
                        } else {
                            // Append new group
                            fragment.appendChild(group);
                            lastGroup = group;
                            lastGroupTitle = groupTitle;
                        }
                    });

                    // Batch insert all new groups
                    if (fragment.childNodes.length > 0) {
                        sentinel.parentNode.insertBefore(fragment, sentinel);
                    }

                    // Update state
                    nextPage++;
                    sentinel.dataset.nextPage = nextPage;

                    // Check if the fetched page has a "next page" indicator in its logic
                    const newSentinel = tempDiv.querySelector('#scroll-sentinel');
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
