#!/bin/bash
# =====================================================
# HEADLESS MOODLE API ENDPOINT AUDIT
# =====================================================

TOKEN="9b3f3d99720dc4cdde123395266d10a8"
BASE="http://localhost:8000/local/api/index.php"
AUTH="-H \"Authorization: Bearer $TOKEN\""
PASS=0
FAIL=0

check() {
    local label="$1"
    local result="$2"
    local status
    status=$(echo "$result" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('status','unknown'))" 2>/dev/null)
    if [ "$status" = "success" ]; then
        echo "✅ $label"
        PASS=$((PASS+1))
    else
        local msg
        msg=$(echo "$result" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('message',d.get('code','?'))[:80])" 2>/dev/null)
        echo "❌ $label → $msg"
        FAIL=$((FAIL+1))
    fi
}

# =====================================================
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo " PUBLIC ENDPOINTS (no token required)"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

check "GET ping" "$(curl -s "$BASE?action=ping")"
check "GET auth_get_social_providers" "$(curl -s "$BASE?action=auth_get_social_providers")"
check "GET auth_get_signup_settings" "$(curl -s "$BASE?action=auth_get_signup_settings")"
check "POST auth_login" "$(curl -s -X POST "$BASE?action=auth_login" -H "Content-Type: application/json" -d '{"username":"admin","password":"Headless@2026"}')"
check "POST auth_signup_user" "$(curl -s -X POST "$BASE?action=auth_signup_user" -H "Content-Type: application/json" -d '{"username":"audituser7","password":"Test@12345","email":"audituser7@example.com","firstname":"Audit","lastname":"User"}')"

# =====================================================
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo " STUDENT / GENERAL ENDPOINTS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

check "GET get_calendar_monthly" "$(curl -s -H "Authorization: Bearer $TOKEN" "$BASE?action=get_calendar_monthly&year=2026&month=4")"
check "GET get_user_notes" "$(curl -s -H "Authorization: Bearer $TOKEN" "$BASE?action=get_user_notes")"
check "GET moodle_ws_proxy (get_courses)" "$(curl -s -H "Authorization: Bearer $TOKEN" "$BASE?action=moodle_ws_proxy&wsfunction=core_enrol_get_users_courses&userid=2")"

# =====================================================
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo " ADMIN ENDPOINTS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

check "GET admin_get_plugins" "$(curl -s -H "Authorization: Bearer $TOKEN" "$BASE?action=admin_get_plugins")"
check "POST admin_set_plugin_status" "$(curl -s -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" "$BASE?action=admin_set_plugin_status" -d '{"plugin":"mod_assign","enabled":1}')"

# =====================================================
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo " INSTRUCTOR ENDPOINTS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

check "GET instructor_get_questions" "$(curl -s -H "Authorization: Bearer $TOKEN" "$BASE?action=instructor_get_questions")"
check "GET instructor_get_grade_items (courseid=2)" "$(curl -s -H "Authorization: Bearer $TOKEN" "$BASE?action=instructor_get_grade_items&courseid=2")"
check "GET instructor_get_progress_report (courseid=2)" "$(curl -s -H "Authorization: Bearer $TOKEN" "$BASE?action=instructor_get_progress_report&courseid=2")"
check "GET instructor_get_user_files" "$(curl -s -H "Authorization: Bearer $TOKEN" "$BASE?action=instructor_get_user_files")"
check "GET instructor_manage_groups (get)" "$(curl -s -H "Authorization: Bearer $TOKEN" "$BASE?action=instructor_manage_groups&courseid=2&groupaction=get")"
check "GET instructor_get_course_participants" "$(curl -s -H "Authorization: Bearer $TOKEN" "$BASE?action=instructor_get_course_participants&courseid=2")"

check "POST instructor_create_question" "$(curl -s -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" "$BASE?action=instructor_create_question" -d '{"name":"Audit Q1","text":"Is the sky blue?","qtype":"truefalse"}')"
check "POST instructor_manage_sections (add)" "$(curl -s -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" "$BASE?action=instructor_manage_sections" -d '{"courseid":2,"sectionaction":"add","sectionnum":0}')"
check "POST instructor_manage_sections (rename)" "$(curl -s -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" "$BASE?action=instructor_manage_sections" -d '{"courseid":2,"sectionaction":"rename","sectionnum":1,"name":"Week 1 - Intro"}')"
check "POST instructor_manage_groups (create)" "$(curl -s -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" "$BASE?action=instructor_manage_groups" -d '{"courseid":2,"groupaction":"create","groups":[{"courseid":2,"name":"Audit Group A","description":"Test group"}]}')"

# =====================================================
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo " RESULTS: $PASS passed / $((PASS+FAIL)) total | $FAIL failed"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
