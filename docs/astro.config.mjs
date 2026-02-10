// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

// https://astro.build/config
export default defineConfig({
	site: 'https://knobik.github.io',
	base: '/sql-agent',
	integrations: [
		starlight({
			title: 'SQL Agent for Laravel',
			social: [{ icon: 'github', label: 'GitHub', href: 'https://github.com/knobik/sql-agent' }],
			sidebar: [
				{ label: 'Getting Started', autogenerate: { directory: 'getting-started' } },
				{ label: 'Guides', autogenerate: { directory: 'guides' } },
				{ label: 'Reference', autogenerate: { directory: 'reference' } },
				{ label: 'Troubleshooting', slug: 'troubleshooting' },
			],
		}),
	],
});
