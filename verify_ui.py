from playwright.sync_api import sync_playwright, expect

def verify_ui():
    with sync_playwright() as p:
        browser = None
        try:
            browser = p.chromium.launch(headless=True)
            page = browser.new_page()

            # Navigate to the app
            page.goto("http://localhost:8080")

            # Check for sidebar existence
            sidebar = page.locator(".sidebar")
            expect(sidebar).to_be_visible()

            # Check computed style for backdrop-filter
            sidebar_backdrop = sidebar.evaluate("element => getComputedStyle(element).backdropFilter")
            webkit_sidebar_backdrop = sidebar.evaluate("element => getComputedStyle(element).webkitBackdropFilter")

            print(f"Computed backdrop-filter: {sidebar_backdrop}")
            print(f"Computed -webkit-backdrop-filter: {webkit_sidebar_backdrop}")

            # Verify glassmorphism
            # The task requires a fallback check if computed style check fails

            is_blur_present = False
            if sidebar_backdrop and "blur" in sidebar_backdrop and "none" not in sidebar_backdrop:
                is_blur_present = True
            elif webkit_sidebar_backdrop and "blur" in webkit_sidebar_backdrop and "none" not in webkit_sidebar_backdrop:
                is_blur_present = True

            if not is_blur_present:
                # Fallback check as per task description
                print("Computed style check failed or not supported. Attempting fallback CSS check...")

                # Context from task:
                # if "blur" not in sidebar_backdrop and "none" not in sidebar_backdrop:
                #     # Note: some browsers might report 'none' if hardware accel issues,
                #     # but standard headless chromium usually reports it.
                #     # However, if it fails, we should check if it's applied in CSS at all.
                #     pass

                # Implementation of the fallback:
                # Check if the CSS rule exists in the stylesheets

                css_check = page.evaluate("""() => {
                    for (const sheet of document.styleSheets) {
                        try {
                            for (const rule of sheet.cssRules) {
                                if (rule.selectorText && rule.selectorText.includes('.sidebar')) {
                                    if ((rule.style.backdropFilter && rule.style.backdropFilter.includes('blur')) ||
                                        (rule.style.webkitBackdropFilter && rule.style.webkitBackdropFilter.includes('blur'))) {
                                        return true;
                                    }
                                }
                            }
                        } catch (e) {
                            // Ignore CORS errors for external stylesheets if any
                        }
                    }
                    return false;
                }""")

                if css_check:
                    print("Fallback: Glassmorphism found in CSS rules.")
                else:
                    # Also check if it uses a variable that might define blur
                    # Since app.css uses var(--blur), checking strict "blur" in style object might fail if it's not resolved in CSSOM the same way
                    # Let's check the cssText

                    css_text_check = page.evaluate("""() => {
                        for (const sheet of document.styleSheets) {
                            try {
                                for (const rule of sheet.cssRules) {
                                    if (rule.selectorText && rule.selectorText.includes('.sidebar')) {
                                        if (rule.cssText.includes('backdrop-filter') && rule.cssText.includes('blur')) {
                                            return true;
                                        }
                                    }
                                }
                            } catch (e) {}
                        }
                        return false;
                    }""")

                    if css_text_check:
                        print("Fallback: Glassmorphism found in CSS text.")
                    else:
                        raise Exception("Glassmorphism (backdrop-filter) not found in computed style OR CSS rules.")
            else:
                print("Glassmorphism verified via computed style.")
        finally:
            if browser:
                browser.close()

if __name__ == "__main__":
    verify_ui()
