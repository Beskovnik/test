from playwright.sync_api import sync_playwright, expect

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    page = browser.new_page()

    # 1. Visit Index
    print("Navigating to index...")
    page.goto("http://localhost:8080")

    # 2. Verify Sidebar Links (Absence)
    print("Verifying sidebar...")
    content = page.content()
    assert "Raziskuj" not in content
    assert "Albumi" not in content
    assert "Priljubljene" not in content
    # Info was linked to settings.php with text 'Info'
    assert "Info" not in content or "settings.php" not in content

    # 3. Verify Header Link
    print("Verifying header...")
    logo = page.locator(".logo")
    expect(logo).to_have_attribute("href", "/index.php")

    # 4. Verify Naming Convention on Index
    # We expect titles like "6 slika" or "7 video"
    # Wait for the grid to load
    page.wait_for_selector(".grid")
    page.screenshot(path="verification/index.png")

    # Check text of first card title
    first_title = page.locator(".card h3").first
    title_text = first_title.inner_text()
    print(f"First title: {title_text}")

    if "slika" not in title_text and "video" not in title_text:
        print("WARNING: Naming convention might be wrong on index.")

    # 5. Visit a Post
    print("Clicking first post...")
    page.locator(".card").first.click()
    page.wait_for_load_state("networkidle")

    # 6. Verify Post Page
    print("Verifying post page...")
    h1 = page.locator("h1")
    h1_text = h1.inner_text()
    print(f"Post H1: {h1_text}")

    if "slika" not in h1_text and "video" not in h1_text:
         print("WARNING: Naming convention might be wrong on view.php.")

    page.screenshot(path="verification/post.png")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)
