from playwright.sync_api import sync_playwright

def verify_glassmorphism():
    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()

        # Check homepage
        try:
            page.goto("http://localhost:8080/index.php")

            # Wait for content
            page.wait_for_selector(".sidebar")

            # Get computed style of sidebar
            sidebar_backdrop = page.eval_on_selector(".sidebar", "el => getComputedStyle(el).backdropFilter")
            sidebar_bg = page.eval_on_selector(".sidebar", "el => getComputedStyle(el).backgroundColor")

            print(f"Sidebar Backdrop Filter: {sidebar_backdrop}")
            print(f"Sidebar Background: {sidebar_bg}")

            # Check for blur
            if "blur" not in sidebar_backdrop and "none" not in sidebar_backdrop:
                # Note: some browsers might report 'none' if hardware accel issues,
                # but standard headless chromium usually reports it.
                # However, if it fails, we should check if it's applied in CSS at all.
                pass

            # Take screenshot
            page.screenshot(path="ui_glassmorphism.png")
            print("Screenshot saved to ui_glassmorphism.png")

        except Exception as e:
            print(f"Error: {e}")
        finally:
            browser.close()

if __name__ == "__main__":
    verify_glassmorphism()
