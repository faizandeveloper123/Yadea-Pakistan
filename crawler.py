import asyncio
import os
from playwright.async_api import async_playwright

BASE = "https://apps.eduportal.pk/Production/"
SAVE_DIR = os.path.join(os.path.dirname(__file__), "crawled")

PAGES = [
    "dashboard.php", "basic_dashboard.php", "front_desk_analytics.php",
    "student_inquiry.php", "manage_complaint.php", "add_student.php",
    "manage_students.php", "students_analytics_dashboard.php", "class_promotion.php",
    "mark_attend.php", "mark_attendanceReport_list.php", "day_attendance_summary.php",
    "new_message.php", "messages_history.php", "view_templates.php",
    "monthly_challan.php", "view_challan_details.php", "multi_fee_reports.php",
    "update_fee_settings.php", "datewise_fee_collection_report_new.php",
    "fee_challans.php", "print_unpaid_fee_new.php", "monthly_invoices.php",
    "view_marksheet.php", "reportcards.php", "manage_exams.php",
    "period_categories.php", "create_period_details.php", "class_period.php",
    "class_period_selection.php", "view_class_period_selection.php",
    "view_teachers_timetable.php", "add_emp.php", "view_emp.php",
    "view_emp_attendance.php", "monthly_attendance.php", "old_employee.php",
    "create_datesheet.php", "view_datesheet.php", "generate_rollnoSlips.php",
    "syllabus_management.php", "vehicles.php", "route.php", "vehicle_route.php",
    "list_books.php", "issue_return.php", "issue_return_employee.php",
    "creat_payroll.php", "view_payroll.php", "staff_security.php", "payroll_setting.php",
    "parents_portal_dashboard.php", "manage_expenses.php", "monthly_expenses_report.php",
    "cards.php", "students_card.php", "canteen_dashboard.php",
    "academic_setup.php", "settings.php", "manage_localities.php",
    "add_revenue.php", "revenue_list.php", "revenue_heads.php",
    "customer_tickets.php", "software_demo_videos.php", "student_birthday.php",
    "update_profile.php", "update_pswd.php",
]

async def run():
    os.makedirs(SAVE_DIR, exist_ok=True)
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        page = await browser.new_page()
        print("Logging in...")
        await page.goto(BASE)
        await page.wait_for_load_state("networkidle")
        await page.locator("input#email").fill("kashif123@gmail.com")
        await page.locator("input#password").fill("kash7395515")
        await page.locator("button.btn-login").click()
        await page.wait_for_load_state("networkidle")
        await page.wait_for_timeout(3000)
        print("Logged in.")

        results = {}
        for pname in PAGES:
            url = BASE + pname
            try:
                await page.goto(url)
                await page.wait_for_load_state("networkidle")
                await page.wait_for_timeout(1500)
                # Check if login page (session expired) -> re-login
                cur = page.url
                if "login" in cur or "index" in cur or "Production/" == cur.rstrip('/'):
                    print(f"!! Session expired at {pname}, re-logging...")
                    await page.locator("input#email").fill("kashif123@gmail.com")
                    await page.locator("input#password").fill("kash7395515")
                    await page.locator("button.btn-login").click()
                    await page.wait_for_load_state("networkidle")
                    await page.wait_for_timeout(2000)
                    await page.goto(url)
                    await page.wait_for_load_state("networkidle")
                    await page.wait_for_timeout(1500)
                content = await page.content()
                safe = pname.replace(".php", "")
                path = os.path.join(SAVE_DIR, safe + ".html")
                with open(path, "w", encoding="utf-8") as f:
                    f.write(content)
                results[pname] = len(content)
                print(f"Saved {pname}: {len(content)} bytes")
            except Exception as e:
                print(f"ERROR {pname}: {e}")
                results[pname] = "ERROR"

        print("\n=== SUMMARY ===")
        for k, v in results.items():
            print(f"{k}: {v}")

        await browser.close()

if __name__ == "__main__":
    asyncio.run(run())
