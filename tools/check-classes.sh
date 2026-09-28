#!/bin/bash
# 标记里用到的每一个 aml-* 类，admin.css 里必须真有规则。
#
# 🔴 2026-09-28 插件线拍截图时发现：Products 那张卡整张用的是不存在的类名 ——
#    aml-card-title / aml-button / aml-input / aml-ok / aml-warn 五个，CSS 里一条规则都没有。
#    表现是标题没图标、按钮是灰的默认样式、输入框只有 180px 把 placeholder 截断，
#    而**每一处单看都像设计如此**。
#
# ⚠️ 为什么非要单独一道：Plugin Check 不看 CSS 类名对不对，PHPCS 不看，php -l 更不看。
#    类名写错了页面照常渲染，只是渲染成别的样子 —— 没有报错、没有警告、没有任何一处会红。
#    它只会在有人真正看那个页面的时候出现，而我们差一点就把它拍进截图发到 wordpress.org 上。
#
# 反向也查：CSS 里定义了却没人用的类（那通常是标记那边改了名字之后留下的残骸）。
set -u
cd "$(dirname "$0")/.."
bad=0

# ⚠️ 扫**所有** PHP，不只是 admin/views：页面外壳那几个类（aml-app / aml-header / aml-logo …）
#    是在 includes/class-aumchat-admin.php 里输出的。第一版只扫 views，于是反向检查
#    把六个正在用的类报成「残骸」—— 受检范围写窄了，闸就会喊狼来了，而喊狼的闸很快就没人看。
used=$(grep -rohE 'class="[^"]*"' --include='*.php' . | grep -ohE 'aml-[a-z-]+' | sort -u)
[ -z "$used" ] && { echo "🔴 一个 aml-* 类都没扫到 —— 路径或正则写坏了"; exit 1; }

for c in $used; do
  n=$(grep -c "\.$c\b" admin/assets/admin.css)
  if [ "$n" = 0 ]; then echo "🔴 标记里用了 .$c，CSS 里没有规则"; bad=$((bad+1)); fi
done

defined=$(grep -ohE '\.aml-[a-z-]+' admin/assets/admin.css | grep -ohE 'aml-[a-z-]+' | sort -u)
for c in $defined; do
  if ! echo "$used" | grep -qx "$c"; then echo "⚠️  CSS 定义了 .$c，但标记里没人用（改名留下的残骸？）"; fi
done

if [ "$bad" = 0 ]; then
  echo "✅ $(echo "$used" | wc -l | tr -d ' ') 个类全部有规则"
else
  echo; echo "🔴 $bad 个类没有规则，页面会渲染成别的样子而不报错"; exit 1
fi
