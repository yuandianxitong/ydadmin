<template>
  <footer class="bg-white border-t border-gray-200 mt-10">
    <!-- Links -->
    <div class="mx-auto max-w-1200px px-4 py-8">
      <div class="grid grid-cols-4 gap-8">
        <div>
          <h4 class="text-sm font-semibold text-gray-700 mb-3">关于我们</h4>
          <ul class="flex flex-col gap-2">
            <li><a href="javascript:;" class="text-sm text-gray-500 hover:text-[var(--color-primary)]">关于元点</a></li>
            <li><a href="javascript:;" class="text-sm text-gray-500 hover:text-[var(--color-primary)]">联系我们</a></li>
          </ul>
        </div>
        <div>
          <h4 class="text-sm font-semibold text-gray-700 mb-3">帮助中心</h4>
          <ul class="flex flex-col gap-2">
            <li><a href="javascript:;" class="text-sm text-gray-500 hover:text-[var(--color-primary)]">用户协议</a></li>
            <li><a href="javascript:;" class="text-sm text-gray-500 hover:text-[var(--color-primary)]">隐私政策</a></li>
          </ul>
        </div>
        <div>
          <h4 class="text-sm font-semibold text-gray-700 mb-3">友情链接</h4>
          <ul class="flex flex-col gap-2">
            <li><a href="/admin/" class="text-sm text-gray-500 hover:text-[var(--color-primary)]">管理后台</a></li>
          </ul>
        </div>
        <div>
          <h4 class="text-sm font-semibold text-gray-700 mb-3">联系方式</h4>
          <ul class="flex flex-col gap-2">
            <li class="text-sm text-gray-500">邮箱：642508814@qq.com</li>
            <li class="text-sm text-gray-500">微信：Vince_Dorian</li>
          </ul>
        </div>
      </div>
    </div>
    <!-- Copyright -->
    <div class="border-t border-gray-100">
      <div class="mx-auto max-w-1200px px-4 py-4 text-center text-xs text-gray-400">
        &copy; {{ new Date().getFullYear() }} 元点Admin. All rights reserved. Powered by yd-admin
      </div>
    </div>
  </footer>
</template>
