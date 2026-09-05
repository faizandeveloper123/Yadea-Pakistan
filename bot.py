import asyncio
from playwright.async_api import async_playwright

async def run_eduportal_login():
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=False)
        page = await browser.new_page()
        
        print("Connecting to EduPortal...")
        await page.goto("https://apps.eduportal.pk/Production/")
        await page.wait_for_load_state("networkidle")
        
        print("Filling credentials...")
        await page.locator("input#email").fill("kashif123@gmail.com")
        
        await page.locator("input#password").fill("kash7395515")
        
        print("Clicking login button...")
        login_button = page.locator("button.btn-login")
        await login_button.click()
        
        await page.wait_for_load_state("networkidle")
        print("Successfully logged in!")
        
        dashboard_content = await page.content()
        with open("dashboard.html", "w", encoding="utf-8") as f:
            f.write(dashboard_content)
        print("Dashboard page saved as 'dashboard.html'")
        
        await page.wait_for_timeout(5000)
        await browser.close()

if __name__ == "__main__":
    asyncio.run(run_eduportal_login())
