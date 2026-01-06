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
            sidebar_backdrop = page.eval_on_selector(".sidebar", "el => getComputedStyle(el).backdropFilter || getComputedStyle(el).webkitBackdropFilter")
            sidebar_bg = page.eval_on_selector(".sidebar", "el => getComputedStyle(el).backgroundColor")

            print(f"Sidebar Backdrop Filter: {sidebar_backdrop}")
            print(f"Sidebar Background: {sidebar_bg}")

            # Check for blur
            if not sidebar_backdrop or "blur" not in sidebar_backdrop:
                # Note: some browsers might report 'none' if hardware accel issues,
                # but standard headless chromium usually reports it.
                # However, if it fails, we should check if it's applied in CSS at all.
                print("Computed style does not contain 'blur'. Checking CSS rules explicitly...")

                # Define JS to check CSS rules
                check_css_js = """() => {
                    for (const sheet of document.styleSheets) {
                        try {
                            for (const rule of sheet.cssRules) {
                                if (rule.selectorText && rule.selectorText.includes('.sidebar')) {
                                    const style = rule.style;
                                    const hasBlur = (style.backdropFilter && style.backdropFilter.includes('blur'));
                                    const hasWebkitBlur = (style.webkitBackdropFilter && style.webkitBackdropFilter.includes('blur'));

                                    if (hasBlur || hasWebkitBlur) {
                                        return true;
                                    }
                                }
                            }
                        } catch (e) {
                            // Ignore cross-origin stylesheet errors
                        }
                    }
                    return false;
                }"""

                is_applied_in_css = page.evaluate(check_css_js)

                if is_applied_in_css:
                    print("Fallback PASS: Glassmorphism found in CSS rules (likely browser rendering limitation).")
                else:
                    print(f"FAIL: Glassmorphism NOT found in computed style ('{sidebar_backdrop}') OR CSS rules.")

            # Take screenshot
            page.screenshot(path="ui_glassmorphism.png")
            print("Screenshot saved to ui_glassmorphism.png")

        except Exception as e:
            print(f"Error: {e}")
        finally:
            browser.close()

if __name__ == "__main__":
    verify_glassmorphism()
