<?php

namespace Nktlksvch\BulbaKit\Services\Install;

use Illuminate\Support\Facades\File;
use Nktlksvch\BulbaKit\DefaultCrud\Contracts\DefaultCrud;
use Nktlksvch\BulbaKit\Modules\Contracts\ModuleInterface;

class NavigationGenerator
{
    /**
     * Generate navigation.ts by merging nav items from default resources and modules.
     *
     * @param  array<int, DefaultCrud>  $features
     * @param  array<int, ModuleInterface>  $modules
     */
    public function generate(array $features, array $modules): void
    {
        $groups = [
            ['label' => 'General', 'items' => [
                ['title' => 'Dashboard', 'href' => '/admin/dashboard', 'icon' => 'layout-grid'],
            ]],
        ];

        foreach ($features as $resource) {
            $groups = $this->mergeNavigation($groups, $resource->navigation());
        }

        foreach ($modules as $module) {
            $groups = $this->mergeNavigation($groups, $module->navigation());
        }

        $content = $this->renderNavigationTs($groups);
        $destination = resource_path('js/navigation.ts');

        File::ensureDirectoryExists(dirname($destination));
        File::put($destination, $content);
    }

    /**
     * Merge navigation groups, combining items under matching group labels.
     *
     * @param  array<int, array{label: string, items: array<int, array{title: string, titleKey?: string, href: string, icon: string}>}>  $groups
     * @param  array<int, array{group: string, items: array<int, array{title: string, titleKey?: string, href: string, icon: string}>}>  $navigation
     * @return array<int, array{label: string, items: array<int, array{title: string, titleKey?: string, href: string, icon: string}>}>
     */
    protected function mergeNavigation(array $groups, array $navigation): array
    {
        foreach ($navigation as $nav) {
            $groupLabel = $nav['group'];
            $found = false;

            foreach ($groups as &$g) {
                if ($g['label'] === $groupLabel) {
                    $g['items'] = array_merge($g['items'], $nav['items']);
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $groups[] = ['label' => $groupLabel, 'items' => $nav['items']];
            }
        }

        return $groups;
    }

    /**
     * @param  array<int, array{label: string, items: array<int, array{title: string, titleKey?: string, href: string, icon: string}>}>  $groups
     */
    protected function renderNavigationTs(array $groups): string
    {
        $lines = [];
        $lines[] = "import type { NavGroup } from '@/types/navigation';";
        $lines[] = '';
        $lines[] = 'export const navigation: NavGroup[] = [';

        foreach ($groups as $group) {
            $lines[] = '    {';
            $lines[] = "        label: '{$group['label']}',";
            $lines[] = '        items: [';
            foreach ($group['items'] as $item) {
                $title = $item['title'];
                $titleKey = $item['titleKey'] ?? $title;
                $lines[] = "            { title: '{$title}', titleKey: '{$titleKey}', href: '{$item['href']}', icon: '{$item['icon']}' },";
            }
            $lines[] = '        ],';
            $lines[] = '    },';
        }

        $lines[] = '];';
        $lines[] = '';

        return implode("\n", $lines);
    }
}
