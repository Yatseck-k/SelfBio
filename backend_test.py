#!/usr/bin/env python3
"""
Backend API Testing for Laravel SelfBio Project
Tests the updated user routes after MoonShine conflict resolution
"""

import requests
import sys
from datetime import datetime

class LaravelAPITester:
    def __init__(self, base_url="http://localhost:8001"):
        self.base_url = base_url
        self.session = requests.Session()
        self.tests_run = 0
        self.tests_passed = 0
        self.csrf_token = None

    def run_test(self, name, method, endpoint, expected_status, data=None, follow_redirects=True):
        """Run a single API test"""
        url = f"{self.base_url}{endpoint}"
        
        self.tests_run += 1
        print(f"\n🔍 Testing {name}...")
        print(f"   URL: {url}")
        
        try:
            # Add CSRF token if we have one
            headers = {
                'User-Agent': 'Mozilla/5.0 (compatible; TestBot/1.0)',
                'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
            }
            
            if self.csrf_token:
                headers['X-CSRF-TOKEN'] = self.csrf_token
                if data:
                    data['_token'] = self.csrf_token

            if method == 'GET':
                response = self.session.get(url, headers=headers, allow_redirects=follow_redirects)
            elif method == 'POST':
                response = self.session.post(url, data=data, headers=headers, allow_redirects=follow_redirects)

            success = response.status_code == expected_status
            if success:
                self.tests_passed += 1
                print(f"✅ Passed - Status: {response.status_code}")
                if response.history:
                    print(f"   Redirected from: {[r.url for r in response.history]}")
                    print(f"   Final URL: {response.url}")
            else:
                print(f"❌ Failed - Expected {expected_status}, got {response.status_code}")
                print(f"   Response URL: {response.url}")
                if response.text:
                    print(f"   Response preview: {response.text[:200]}...")

            return success, response

        except Exception as e:
            print(f"❌ Failed - Error: {str(e)}")
            return False, None

    def get_csrf_token(self):
        """Get CSRF token from the main page"""
        print("\n🔑 Getting CSRF token...")
        try:
            response = self.session.get(f"{self.base_url}/")
            if response.status_code == 200:
                # Look for CSRF token in meta tag
                import re
                csrf_match = re.search(r'<meta name="csrf-token" content="([^"]+)"', response.text)
                if csrf_match:
                    self.csrf_token = csrf_match.group(1)
                    print(f"✅ CSRF token obtained: {self.csrf_token[:20]}...")
                    return True
                else:
                    print("⚠️  No CSRF token found in response")
            return False
        except Exception as e:
            print(f"❌ Failed to get CSRF token: {str(e)}")
            return False

    def test_main_page(self):
        """Test main page loads correctly"""
        success, response = self.run_test(
            "Main Page Load",
            "GET",
            "/",
            200
        )
        return success and response

    def test_dashboard_redirect(self):
        """Test that dashboard button redirects to /enter"""
        success, response = self.run_test(
            "Dashboard Redirect to /enter",
            "GET",
            "/enter",
            200
        )
        return success and response

    def test_login_page(self):
        """Test login page loads at /enter"""
        success, response = self.run_test(
            "Login Page at /enter",
            "GET",
            "/enter",
            200
        )
        if success and response:
            # Check if it contains login form elements
            if 'email' in response.text and 'password' in response.text:
                print("   ✅ Login form elements found")
                return True
            else:
                print("   ⚠️  Login form elements not found")
        return success

    def test_login_authentication(self):
        """Test login with test credentials"""
        # First get CSRF token
        if not self.csrf_token:
            self.get_csrf_token()
        
        login_data = {
            'email': 'test@test.com',
            'password': 'password',
            'remember': False
        }
        
        success, response = self.run_test(
            "Login Authentication",
            "POST",
            "/enter",
            302,  # Expect redirect after successful login
            data=login_data,
            follow_redirects=False
        )
        
        if success and response:
            # Check if redirected to /home
            location = response.headers.get('Location', '')
            if '/home' in location:
                print("   ✅ Redirected to /home as expected")
                return True
            else:
                print(f"   ⚠️  Redirected to {location}, expected /home")
        
        return success

    def test_dashboard_access(self):
        """Test dashboard access at /home (requires authentication)"""
        success, response = self.run_test(
            "Dashboard Access at /home",
            "GET",
            "/home",
            200
        )
        
        if success and response:
            # Check for dashboard content
            if 'дашборд' in response.text.lower() or 'dashboard' in response.text.lower():
                print("   ✅ Dashboard content found")
                return True
            else:
                print("   ⚠️  Dashboard content not clearly identified")
        
        return success

    def test_moonshine_admin_separation(self):
        """Test that MoonShine admin is accessible at /admin"""
        success, response = self.run_test(
            "MoonShine Admin Access",
            "GET",
            "/admin",
            200
        )
        
        if success and response:
            # Check for MoonShine admin content
            if 'moonshine' in response.text.lower() or 'admin' in response.text.lower():
                print("   ✅ Admin interface accessible")
                return True
            else:
                print("   ⚠️  Admin interface content not clearly identified")
        
        return success

    def test_other_pages(self):
        """Test other pages like contacts and blog"""
        pages = ['/contacts', '/blog']
        all_passed = True
        
        for page in pages:
            success, response = self.run_test(
                f"Page {page}",
                "GET",
                page,
                200
            )
            if not success:
                all_passed = False
        
        return all_passed

    def test_logout_functionality(self):
        """Test logout functionality"""
        success, response = self.run_test(
            "Logout Functionality",
            "POST",
            "/logout",
            302,  # Expect redirect after logout
            follow_redirects=False
        )
        
        if success and response:
            location = response.headers.get('Location', '')
            if location == '/' or location.endswith('/'):
                print("   ✅ Redirected to home page after logout")
                return True
            else:
                print(f"   ⚠️  Redirected to {location}, expected home page")
        
        return success

def main():
    """Main test execution"""
    print("🚀 Starting Laravel SelfBio Backend API Tests")
    print("=" * 60)
    
    tester = LaravelAPITester("http://localhost:8001")
    
    # Test sequence
    tests = [
        ("Main Page", tester.test_main_page),
        ("Login Page", tester.test_login_page),
        ("Login Authentication", tester.test_login_authentication),
        ("Dashboard Access", tester.test_dashboard_access),
        ("MoonShine Admin", tester.test_moonshine_admin_separation),
        ("Other Pages", tester.test_other_pages),
        ("Logout", tester.test_logout_functionality),
    ]
    
    for test_name, test_func in tests:
        try:
            test_func()
        except Exception as e:
            print(f"❌ {test_name} failed with exception: {str(e)}")
    
    # Print final results
    print("\n" + "=" * 60)
    print(f"📊 Test Results: {tester.tests_passed}/{tester.tests_run} tests passed")
    
    if tester.tests_passed == tester.tests_run:
        print("🎉 All tests passed!")
        return 0
    else:
        print("⚠️  Some tests failed. Check the output above for details.")
        return 1

if __name__ == "__main__":
    sys.exit(main())