from playwright.sync_api import sync_playwright

def verify_gallery():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Navigate to homepage
        page.goto("http://localhost:8080/index.php")

        # Take screenshot of the grid (infinite scroll initial load)
        page.screenshot(path="verification/gallery_grid.png", full_page=True)
        print("Gallery grid screenshot saved.")

        # Click on the first card if exists
        card = page.locator(".card").first
        if card.count() > 0:
            card.click()
            page.wait_for_load_state("networkidle")

            # Take screenshot of the view page (checking preview/original logic visually)
            page.screenshot(path="verification/view_page.png", full_page=True)
            print("View page screenshot saved.")

        browser.close()

if __name__ == "__main__":
    verify_gallery()
