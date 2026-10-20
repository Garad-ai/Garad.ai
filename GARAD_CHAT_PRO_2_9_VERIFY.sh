#!/usr/bin/env bash
set -euo pipefail

ok(){ printf 'PASS  %s\n' "$1"; }
fail(){ printf 'FAIL  %s\n' "$1" >&2; exit 1; }

# Critical PHP syntax
for f in \
  app/Extensions/AIChatPro/System/AIChatProServiceProvider.php \
  app/Extensions/AIChatPro/System/Http/Controllers/WorkspaceController.php \
  app/Extensions/AIChatPro/System/Services/AiChatProService.php \
  app/Extensions/AIChatPro/System/Services/GaradAgentToolService.php \
  app/Extensions/AIChatPro/System/Services/GaradPresentationToolServiceV5.php \
  app/Extensions/AIChatPro/System/Services/GaradVideoToolServiceV5.php \
  app/Extensions/AiPresentation/System/Services/GammaService.php \
  app/Extensions/AiVideoPro/System/Http/Controllers/AiVideoProController.php \
  app/Http/Controllers/AIChatController.php \
  app/Services/Stream/StreamService.php \
  app/Services/Services/Stream/StreamService.php; do
  test -f "$f" || fail "missing $f"
  php -l "$f" >/dev/null || fail "php syntax $f"
done
ok 'critical PHP syntax'

# JS syntax
for f in \
  public/themes/default/assets/js/panel/openai_chat.js \
  public/themes/default/assets/js/panel/garad_chatpro_v63.js \
  public/vendor/garad-chat-pro-2/chat-pro-2.js; do
  test -f "$f" || fail "missing $f"
  node --check "$f" >/dev/null || fail "JavaScript syntax $f"
done
ok 'Chat Pro JavaScript syntax'

# Auth and dashboard controls
grep -q "data-garad-login-button" app/Extensions/AIChatPro/resources/views/includes/header/header-right-col.blade.php || fail 'Login button'
grep -q "data-garad-signup-button" app/Extensions/AIChatPro/resources/views/includes/header/header-right-col.blade.php || fail 'Sign Up button'
grep -q "data-garad-dashboard-button" app/Extensions/AIChatPro/resources/views/includes/header/header-right-col.blade.php || fail 'Dashboard button'
grep -q "name('login')" routes/auth.php || fail 'login route'
grep -q "name('register')" routes/auth.php || fail 'register route'
test -f resources/views/default/panel/authentication/login.blade.php || fail 'login view'
test -f resources/views/default/panel/authentication/register.blade.php || fail 'register view'
ok 'Login / Sign Up / Dashboard restoration'

# Model selector / modes
grep -q "components.select-ai-model-list" app/Extensions/AIChatPro/resources/views/includes/header/header-left-col.blade.php || fail 'header model selector'
grep -q "is_object(\$engine)" resources/views/default/components/select-ai-model-list-chat-pro.blade.php || fail 'string/object model normalization'
grep -q "'low'" resources/views/default/components/select-ai-model-list-chat-pro.blade.php || fail 'Low reasoning mode'
grep -q "'medium'" resources/views/default/components/select-ai-model-list-chat-pro.blade.php || fail 'Medium reasoning mode'
grep -q "'high'" resources/views/default/components/select-ai-model-list-chat-pro.blade.php || fail 'High reasoning mode'
grep -q "'max'" resources/views/default/components/select-ai-model-list-chat-pro.blade.php || fail 'Max reasoning mode'
grep -q "reasoning_level" public/themes/default/assets/js/panel/openai_chat.js || fail 'reasoning frontend wiring'
grep -q "resolveReasoningEffort" app/Services/Stream/StreamService.php || fail 'reasoning backend wiring'
ok 'real model selector + reasoning modes'

# Header no longer hidden in final override
tail -n 180 public/vendor/garad-chat-pro-2/chat-pro-2.css | grep -q "restore real header/auth/model controls" || fail '2.9 header CSS override'
tail -n 180 public/vendor/garad-chat-pro-2/chat-pro-2.css | grep -q "display:flex!important" || fail 'visible Chat Pro header'
ok 'Chat Pro header visibility'

# Core Chat Pro workspace
grep -q "workspace.agents" app/Extensions/AIChatPro/resources/views/includes/garad-primary-nav.blade.php || fail 'Agents nav'
grep -q "workspace.library" app/Extensions/AIChatPro/resources/views/includes/garad-primary-nav.blade.php || fail 'Library nav'
grep -q "scheduled.index" app/Extensions/AIChatPro/resources/views/includes/garad-primary-nav.blade.php || fail 'Scheduled nav'
grep -q "Garad Code" app/Extensions/AIChatPro/resources/views/includes/garad-primary-nav.blade.php || fail 'Garad Code nav'
grep -q "WorkflowEngine" app/Extensions/AIChatPro/System/Services/GaradAgentToolService.php || fail 'Agent WorkflowEngine'
ok 'workspace and Agents wiring'

# File/content tools
for tool in create_pdf create_docx create_xlsx create_presentation generate_image generate_video; do
  grep -q "$tool" app/Extensions/AIChatPro/System/Services/AiChatProService.php || fail "$tool tool"
done
ok 'PDF/DOCX/XLSX/PPTX/Image/Video tool routing'

# Video / presentation async fixes
grep -q 'GARAD_PRESENTATION_JOB' public/themes/default/assets/js/panel/garad_chatpro_v63.js || fail 'presentation async marker'
grep -q 'setTimeout(tick, 5000)' public/themes/default/assets/js/panel/garad_chatpro_v63.js || fail 'presentation polling interval'
grep -q 'JOB_TIMEOUT_MINUTES = 60' app/Extensions/AiVideoPro/System/Http/Controllers/AiVideoProController.php || fail 'video timeout guard'
grep -q "'videoData' =>" app/Extensions/AiVideoPro/System/Http/Controllers/AiVideoProController.php || fail 'video terminal payload'
grep -q 'setInterval(checkVideoStatus, 4000)' app/Extensions/AiVideoPro/resources/views/scripts/check-video-status.blade.php || fail 'video polling interval'
ok 'video + presentation polling safeguards'

# Temp chat / edit
grep -q "chatpro-temp" app/Http/Controllers/AIChatController.php || fail 'Temporary Chat support'
grep -q "edit_message_id" public/themes/default/assets/js/panel/openai_chat.js || fail 'edit message frontend'
grep -q "edit_message_id" app/Http/Controllers/OpenAi/GeneratorController.php || fail 'edit message backend'
ok 'Temporary Chat + edit message wiring'

# Duplicate StreamService guard
cmp -s app/Services/Stream/StreamService.php app/Services/Services/Stream/StreamService.php || fail 'StreamService copies differ'
ok 'StreamService copies identical'


# Optional live Laravel route checks when installed at project root.
if [ -f artisan ] && [ -f vendor/autoload.php ]; then
  php artisan route:list --name=login --no-ansi 2>/dev/null | grep -q 'login' || fail 'live login route'
  php artisan route:list --name=register --no-ansi 2>/dev/null | grep -q 'register' || fail 'live register route'
  php artisan route:list --name=dashboard.user.index --no-ansi 2>/dev/null | grep -q 'dashboard.user.index' || fail 'live Dashboard route'
  php artisan route:list --name=dashboard.user.openai.chat.pro.index --no-ansi 2>/dev/null | grep -q 'dashboard.user.openai.chat.pro.index' || fail 'live Chat Pro route'
  ok 'live Laravel routes'
fi

printf '\nALL STATIC CHECKS PASSED\n'
