# Graph Report - .  (2026-05-28)

## Corpus Check
- 271 files · ~67,902 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 764 nodes · 1109 edges · 174 communities (149 shown, 25 thin omitted)
- Extraction: 99% EXTRACTED · 1% INFERRED · 0% AMBIGUOUS · INFERRED: 15 edges (avg confidence: 0.8)
- Token cost: 10,000 input · 5,000 output

## Community Hubs (Navigation)
- [[_COMMUNITY_Community 0|Community 0]]
- [[_COMMUNITY_Community 1|Community 1]]
- [[_COMMUNITY_Community 2|Community 2]]
- [[_COMMUNITY_Community 3|Community 3]]
- [[_COMMUNITY_Community 4|Community 4]]
- [[_COMMUNITY_Community 5|Community 5]]
- [[_COMMUNITY_Community 6|Community 6]]
- [[_COMMUNITY_Community 7|Community 7]]
- [[_COMMUNITY_Community 8|Community 8]]
- [[_COMMUNITY_Community 9|Community 9]]
- [[_COMMUNITY_Community 10|Community 10]]
- [[_COMMUNITY_Community 11|Community 11]]
- [[_COMMUNITY_Community 12|Community 12]]
- [[_COMMUNITY_Community 13|Community 13]]
- [[_COMMUNITY_Community 14|Community 14]]
- [[_COMMUNITY_Community 15|Community 15]]
- [[_COMMUNITY_Community 16|Community 16]]
- [[_COMMUNITY_Community 17|Community 17]]
- [[_COMMUNITY_Community 18|Community 18]]
- [[_COMMUNITY_Community 19|Community 19]]
- [[_COMMUNITY_Community 20|Community 20]]
- [[_COMMUNITY_Community 21|Community 21]]
- [[_COMMUNITY_Community 22|Community 22]]
- [[_COMMUNITY_Community 23|Community 23]]
- [[_COMMUNITY_Community 24|Community 24]]
- [[_COMMUNITY_Community 25|Community 25]]
- [[_COMMUNITY_Community 26|Community 26]]
- [[_COMMUNITY_Community 27|Community 27]]
- [[_COMMUNITY_Community 28|Community 28]]
- [[_COMMUNITY_Community 29|Community 29]]
- [[_COMMUNITY_Community 30|Community 30]]
- [[_COMMUNITY_Community 31|Community 31]]
- [[_COMMUNITY_Community 32|Community 32]]
- [[_COMMUNITY_Community 33|Community 33]]
- [[_COMMUNITY_Community 34|Community 34]]
- [[_COMMUNITY_Community 35|Community 35]]
- [[_COMMUNITY_Community 37|Community 37]]
- [[_COMMUNITY_Community 38|Community 38]]
- [[_COMMUNITY_Community 40|Community 40]]
- [[_COMMUNITY_Community 41|Community 41]]
- [[_COMMUNITY_Community 42|Community 42]]
- [[_COMMUNITY_Community 46|Community 46]]
- [[_COMMUNITY_Community 48|Community 48]]
- [[_COMMUNITY_Community 49|Community 49]]
- [[_COMMUNITY_Community 50|Community 50]]
- [[_COMMUNITY_Community 51|Community 51]]
- [[_COMMUNITY_Community 52|Community 52]]
- [[_COMMUNITY_Community 53|Community 53]]
- [[_COMMUNITY_Community 54|Community 54]]
- [[_COMMUNITY_Community 55|Community 55]]
- [[_COMMUNITY_Community 56|Community 56]]
- [[_COMMUNITY_Community 57|Community 57]]
- [[_COMMUNITY_Community 58|Community 58]]
- [[_COMMUNITY_Community 173|Community 173]]

## God Nodes (most connected - your core abstractions)
1. `cn()` - 127 edges
2. `compilerOptions` - 15 edges
3. `Button()` - 15 edges
4. `scripts` - 12 edges
5. `PowerBiDataTransformer` - 12 edges
6. `require-dev` - 11 edges
7. `scripts` - 9 edges
8. `PowerBiService` - 9 edges
9. `Card()` - 9 edges
10. `FakePowerBiData` - 8 edges

## Surprising Connections (you probably didn't know these)
- `cn()` --calls--> `clsx`  [INFERRED]
  resources/js/lib/utils.ts → package.json
- `TextLink()` --calls--> `cn()`  [EXTRACTED]
  resources/js/components/text-link.tsx → resources/js/lib/utils.ts
- `InputError()` --calls--> `cn()`  [EXTRACTED]
  resources/js/components/input-error.tsx → resources/js/lib/utils.ts
- `CardFooter()` --calls--> `cn()`  [EXTRACTED]
  resources/js/components/ui/card.tsx → resources/js/lib/utils.ts
- `NavigationMenuContent()` --calls--> `cn()`  [EXTRACTED]
  resources/js/components/ui/navigation-menu.tsx → resources/js/lib/utils.ts

## Communities (174 total, 25 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.06
Nodes (36): Login(), Props, Props, InputError(), Props, TextLink(), Props, TwoFactorSetupStep() (+28 more)

### Community 1 - "Community 1"
Cohesion: 0.08
Nodes (16): AppContent(), Props, AppShell(), Props, AppSidebar(), AppSidebarHeader(), Breadcrumbs(), Breadcrumb() (+8 more)

### Community 2 - "Community 2"
Cohesion: 0.11
Nodes (24): Campaign, CampaignSelector(), DashboardSkeleton(), EmailAnalytics(), EmailAnalyticsData, EmailAnalyticsProps, EmailDetailsPanel(), EmailDetailsPanelProps (+16 more)

### Community 3 - "Community 3"
Cohesion: 0.06
Nodes (36): dependencies, class-variance-authority, clsx, concurrently, globals, @headlessui/react, @inertiajs/react, @inertiajs/vite (+28 more)

### Community 4 - "Community 4"
Cohesion: 0.09
Nodes (23): AppearanceToggleTab(), Appearance, applyTheme(), getStoredAppearance(), handleSystemThemeChange(), initializeTheme(), isDarkMode(), listeners (+15 more)

### Community 5 - "Community 5"
Cohesion: 0.06
Nodes (34): devDependencies, babel-plugin-react-compiler, eslint, eslint-config-prettier, eslint-import-resolver-typescript, @eslint/js, eslint-plugin-import, eslint-plugin-react (+26 more)

### Community 6 - "Community 6"
Cohesion: 0.16
Nodes (21): cn(), Sheet(), SheetContent(), SheetDescription(), SheetFooter(), SheetHeader(), SheetOverlay(), SheetTitle() (+13 more)

### Community 7 - "Community 7"
Cohesion: 0.13
Nodes (16): Props, UserMenuContent(), CleanupFn, useMobileNavigation(), DropdownMenu(), DropdownMenuCheckboxItem(), DropdownMenuContent(), DropdownMenuGroup() (+8 more)

### Community 8 - "Community 8"
Cohesion: 0.13
Nodes (3): PowerBiController, PowerBiDataTransformerTest, PowerBiDataTransformer

### Community 9 - "Community 9"
Cohesion: 0.19
Nodes (14): AppHeader(), mainNavItems, Props, rightNavItems, UserInfo(), GetInitialsFn, useInitials(), Avatar() (+6 more)

### Community 10 - "Community 10"
Cohesion: 0.11
Nodes (17): aliases, components, hooks, lib, ui, utils, iconLibrary, rsc (+9 more)

### Community 11 - "Community 11"
Cohesion: 0.11
Nodes (17): compilerOptions, allowJs, baseUrl, esModuleInterop, forceConsistentCasingInFileNames, isolatedModules, jsx, module (+9 more)

### Community 12 - "Community 12"
Cohesion: 0.18
Nodes (14): footerNavItems, mainNavItems, NavFooter(), NavMain(), SidebarContent(), SidebarFooter(), SidebarGroup(), SidebarGroupContent() (+6 more)

### Community 13 - "Community 13"
Cohesion: 0.19
Nodes (11): IsCurrentOrParentUrlFn, IsCurrentUrlFn, useCurrentUrl(), UseCurrentUrlReturn, WhenCurrentUrlFn, toUrl(), SettingsLayout(), sidebarNavItems (+3 more)

### Community 15 - "Community 15"
Cohesion: 0.22
Nodes (10): CampaignSelectorProps, Select(), SelectContent(), SelectItem(), SelectLabel(), SelectScrollDownButton(), SelectScrollUpButton(), SelectSeparator() (+2 more)

### Community 16 - "Community 16"
Cohesion: 0.17
Nodes (7): ProfileController, Auth, TwoFactorSecretKey, TwoFactorSetupData, User, InertiaConfig, InputHTMLAttributes

### Community 17 - "Community 17"
Cohesion: 0.17
Nodes (12): scripts, ci:check, dev, lint, lint:check, post-autoload-dump, post-create-project-cmd, post-root-package-install (+4 more)

### Community 18 - "Community 18"
Cohesion: 0.18
Nodes (10): autoload-dev, psr-4, description, keywords, license, minimum-stability, name, Tests\\ (+2 more)

### Community 19 - "Community 19"
Cohesion: 0.18
Nodes (11): require-dev, fakerphp/faker, laravel/boost, laravel/pail, laravel/pao, laravel/pint, laravel/sail, mockery/mockery (+3 more)

### Community 20 - "Community 20"
Cohesion: 0.22
Nodes (9): NavigationMenu(), NavigationMenuContent(), NavigationMenuIndicator(), NavigationMenuItem(), NavigationMenuLink(), NavigationMenuList(), NavigationMenuTrigger(), navigationMenuTriggerStyle (+1 more)

### Community 21 - "Community 21"
Cohesion: 0.25
Nodes (7): agents, cloud, guidelines, mcp, nightwatch, sail, skills

### Community 23 - "Community 23"
Cohesion: 0.29
Nodes (3): NavUser(), useIsMobile(), SidebarProvider()

### Community 24 - "Community 24"
Cohesion: 0.43
Nodes (5): ToggleGroup(), ToggleGroupContext, ToggleGroupItem(), Toggle(), toggleVariants

### Community 26 - "Community 26"
Cohesion: 0.48
Nodes (4): Alert(), AlertDescription(), AlertTitle(), alertVariants

### Community 27 - "Community 27"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 28 - "Community 28"
Cohesion: 0.29
Nodes (7): require, inertiajs/inertia-laravel, laravel/fortify, laravel/framework, laravel/tinker, laravel/wayfinder, php

### Community 33 - "Community 33"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 35 - "Community 35"
Cohesion: 0.83
Nodes (3): emailRules(), nameRules(), profileRules()

### Community 53 - "Community 53"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

## Knowledge Gaps
- **199 isolated node(s):** `agents`, `cloud`, `guidelines`, `mcp`, `nightwatch` (+194 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **25 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `cn()` connect `Community 6` to `Community 0`, `Community 1`, `Community 2`, `Community 3`, `Community 4`, `Community 7`, `Community 9`, `Community 12`, `Community 13`, `Community 15`, `Community 20`, `Community 23`, `Community 24`, `Community 26`?**
  _High betweenness centrality (0.170) - this node is a cross-community bridge._
- **Why does `dependencies` connect `Community 3` to `Community 5`?**
  _High betweenness centrality (0.083) - this node is a cross-community bridge._
- **Why does `clsx` connect `Community 3` to `Community 6`?**
  _High betweenness centrality (0.078) - this node is a cross-community bridge._
- **What connects `agents`, `cloud`, `guidelines` to the rest of the system?**
  _199 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Community 0` be split into smaller, more focused modules?**
  _Cohesion score 0.06196291270918137 - nodes in this community are weakly interconnected._
- **Should `Community 1` be split into smaller, more focused modules?**
  _Cohesion score 0.08108108108108109 - nodes in this community are weakly interconnected._
- **Should `Community 2` be split into smaller, more focused modules?**
  _Cohesion score 0.10810810810810811 - nodes in this community are weakly interconnected._