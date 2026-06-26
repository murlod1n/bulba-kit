<?php

namespace Nktlksvch\BulbaKit\Services\Install;

class JsInfrastructureInstaller
{
    public function __construct(
        private readonly InstallHelper $helper,
    ) {}

    public function installNpmDependencies(): void
    {
        $packageJsonPath = base_path('package.json');
        if (file_exists($packageJsonPath)) {
            $packageJson = json_decode(file_get_contents($packageJsonPath), true);
            if (isset($packageJson['dependencies']['react'])) {
                return;
            }
        }

        $packages = [
            'react', 'react-dom', '@inertiajs/react', '@inertiajs/vite',
            'tailwindcss', '@tailwindcss/vite', 'tw-animate-css',
            '@vitejs/plugin-react', 'laravel-vite-plugin', 'vite', 'typescript',
            'clsx', 'tailwind-merge', 'class-variance-authority',
            'lucide-react', 'sonner', 'input-otp',
            'babel-plugin-react-compiler',
            '@laravel/passkeys',
            '@base-ui/react',
            'react-dropzone',
            '@dnd-kit/core',
            '@dnd-kit/sortable',
            '@dnd-kit/utilities',
        ];

        $devPackages = [
            '@types/react', '@types/react-dom', '@types/node',
            '@laravel/vite-plugin-wayfinder',
            'globals', 'concurrently',
            '@eslint/js@^9.0.0',
            '@stylistic/eslint-plugin@^4.0.0',
            'eslint@^9.0.0',
            'eslint-config-prettier@^10.0.0',
            'eslint-import-resolver-typescript@^3.7.0',
            'eslint-plugin-import@^2.31.0',
            'eslint-plugin-react@^7.37.0',
            'eslint-plugin-react-hooks@^5.0.0',
            'prettier', 'prettier-plugin-tailwindcss',
            'typescript-eslint@^8.0.0',
        ];

        $this->helper->executeCommand('npm install '.implode(' ', $packages));
        $this->helper->executeCommand('npm install -D '.implode(' ', $devPackages));
    }

    /**
     * @return array<int, string>
     */
    public function initShadcn(): array
    {
        $componentsJsonPath = base_path('components.json');
        if (file_exists($componentsJsonPath) && ! $this->helper->force()) {
            // skip
        } else {
            if (file_exists($componentsJsonPath)) {
                unlink($componentsJsonPath);
            }

            $this->helper->executeCommand('npx shadcn@latest init --base base --preset b3lno3MAK --template next --yes --force');
        }

        $components = [
            'button', 'card', 'input', 'label', 'select', 'separator', 'badge',
            'sidebar', 'skeleton', 'avatar', 'dropdown-menu', 'dialog', 'tooltip',
            'toggle', 'toggle-group', 'collapsible', 'navigation-menu', 'breadcrumb', 'sheet',
            'checkbox', 'input-otp', 'sonner', 'spinner', 'alert', 'alert-dialog',
            'field', 'table', 'textarea', 'pagination', 'tabs',
        ];

        $failed = [];

        foreach ($components as $component) {
            $result = $this->helper->executeCommand("npx shadcn@latest add {$component} --yes --overwrite");
            if (! $result) {
                sleep(2);
                $result = $this->helper->executeCommand("npx shadcn@latest add {$component} --yes --overwrite");
                if (! $result) {
                    $failed[] = $component;
                }
            }
        }

        return $failed;
    }

    public function createConfigFiles(): void
    {
        $this->helper->copyStubIfNotExists('config/tsconfig.json.stub', base_path('tsconfig.json'));
        $this->helper->copyStubIfNotExists('config/vite.config.ts.stub', base_path('vite.config.ts'));

        $this->helper->copyStub('config/.npmrc.stub', base_path('.npmrc'));
        $this->helper->copyStub('config/.prettierignore.stub', base_path('.prettierignore'));
        $this->helper->copyStub('config/.prettierrc.stub', base_path('.prettierrc'));
        $this->helper->copyStub('config/eslint.config.js.stub', base_path('eslint.config.js'));
        $this->helper->copyStub('config/phpstan.neon.stub', base_path('phpstan.neon'));
        $this->helper->copyStub('config/pint.json.stub', base_path('pint.json'));

        if ($this->helper->isCommandAvailable('pnpm')) {
            $this->helper->copyStub('config/pnpm-workspace.yaml.stub', base_path('pnpm-workspace.yaml'));
        }

        $this->updateEnvExample();
    }

    public function createCss(): void
    {
        $this->helper->copyStubIfNotExists('css/app.css.stub', resource_path('css/app.css'));
    }

    public function createJsInfrastructure(): void
    {
        $this->helper->copyStubIfNotExists('js/app.tsx.stub', resource_path('js/app.tsx'));
        $this->helper->copyStubIfNotExists('js/lib/utils.ts.stub', resource_path('js/lib/utils.ts'));
        $this->helper->copyStubIfNotExists('js/lib/icon-map.ts.stub', resource_path('js/lib/icon-map.ts'));

        $types = ['index.ts', 'auth.ts', 'navigation.ts', 'ui.ts', 'global.d.ts', 'vite-env.d.ts'];
        foreach ($types as $type) {
            $this->helper->copyStubIfNotExists("js/types/{$type}.stub", resource_path("js/types/{$type}"));
        }

        $hooks = [
            'use-appearance.tsx', 'use-clipboard.ts', 'use-current-url.ts',
            'use-flash-toast.ts', 'use-initials.tsx', 'use-mobile.tsx',
            'use-mobile-navigation.ts', 'use-two-factor-auth.ts',
            'use-trans.ts',
        ];
        foreach ($hooks as $hook) {
            $this->helper->copyStubIfNotExists("js/hooks/{$hook}.stub", resource_path("js/hooks/{$hook}"));
        }
    }

    public function createComponents(): void
    {
        // Layouts
        $this->helper->copyStubIfNotExists('js/layouts/app-layout.tsx.stub', resource_path('js/layouts/app-layout.tsx'));
        $this->helper->copyStubIfNotExists('js/layouts/auth-layout.tsx.stub', resource_path('js/layouts/auth-layout.tsx'));
        $this->helper->copyStubIfNotExists('js/layouts/auth/auth-simple-layout.tsx.stub', resource_path('js/layouts/auth/auth-simple-layout.tsx'));
        $this->helper->copyStubIfNotExists('js/layouts/auth/auth-card-layout.tsx.stub', resource_path('js/layouts/auth/auth-card-layout.tsx'));
        $this->helper->copyStubIfNotExists('js/layouts/auth/auth-split-layout.tsx.stub', resource_path('js/layouts/auth/auth-split-layout.tsx'));
        $this->helper->copyStubIfNotExists('js/layouts/app/app-sidebar-layout.tsx.stub', resource_path('js/layouts/app/app-sidebar-layout.tsx'));
        $this->helper->copyStubIfNotExists('js/layouts/app/app-header-layout.tsx.stub', resource_path('js/layouts/app/app-header-layout.tsx'));
        $this->helper->copyStubIfNotExists('js/layouts/settings/layout.tsx.stub', resource_path('js/layouts/settings/layout.tsx'));

        // Components
        $components = [
            'alert-error.tsx', 'app-content.tsx', 'app-header.tsx',
            'app-logo-icon.tsx', 'app-logo.tsx', 'app-shell.tsx',
            'app-sidebar-header.tsx', 'app-sidebar.tsx', 'appearance-tabs.tsx',
            'breadcrumbs.tsx', 'delete-user.tsx', 'heading.tsx',
            'input-error.tsx', 'nav-footer.tsx', 'nav-main.tsx',
            'nav-user.tsx', 'password-input.tsx', 'text-link.tsx',
            'user-info.tsx', 'user-menu-content.tsx',
            'manage-passkeys.tsx', 'manage-two-factor.tsx',
            'passkey-item.tsx', 'passkey-register.tsx', 'passkey-verify.tsx',
            'two-factor-recovery-codes.tsx', 'two-factor-setup-modal.tsx',
            'ui/image-upload.tsx',
            'ui/gallery-upload.tsx',
            'ui/locale-switcher.tsx',
        ];
        foreach ($components as $component) {
            $this->helper->copyStubIfNotExists(
                "js/components/{$component}.stub",
                resource_path("js/components/{$component}")
            );
        }
    }

    public function createPages(): void
    {
        $adminPagesPath = 'js/pages/admin';

        $this->helper->copyStubIfNotExists('js/pages/dashboard.tsx.stub', resource_path("{$adminPagesPath}/dashboard.tsx"));
        $this->helper->copyStubIfNotExists('js/pages/welcome.tsx.stub', resource_path("{$adminPagesPath}/welcome.tsx"));

        $authPages = [
            'login.tsx', 'register.tsx', 'forgot-password.tsx',
            'reset-password.tsx', 'verify-email.tsx',
            'two-factor-challenge.tsx', 'confirm-password.tsx',
        ];
        foreach ($authPages as $page) {
            $this->helper->copyStubIfNotExists(
                "js/pages/auth/{$page}.stub",
                resource_path("{$adminPagesPath}/auth/{$page}")
            );
        }

        $settingsPages = ['profile.tsx', 'security.tsx', 'appearance.tsx'];
        foreach ($settingsPages as $page) {
            $this->helper->copyStubIfNotExists(
                "js/pages/settings/{$page}.stub",
                resource_path("{$adminPagesPath}/settings/{$page}")
            );
        }
    }

    protected function updateEnvExample(): void
    {
        $envExamplePath = base_path('.env.example');
        if (! file_exists($envExamplePath)) {
            return;
        }

        $content = file_get_contents($envExamplePath);
        if (! str_contains($content, 'VITE_APP_NAME')) {
            $content .= "\nVITE_APP_NAME=\"\${APP_NAME}\"\n";
            file_put_contents($envExamplePath, $content);
        }
    }
}
