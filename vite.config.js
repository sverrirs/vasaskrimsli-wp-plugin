import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Builds the plugin's React apps into build/ as ES modules with stable file names,
// loaded by includes/Assets.php. React is bundled (19.x), independent of WordPress's copy.
export default defineConfig( {
	plugins: [ react() ],
	base: './',
	publicDir: false,
	build: {
		outDir: 'build',
		emptyOutDir: true,
		target: 'es2020',
		cssCodeSplit: true,
		rolldownOptions: {
			input: {
				admin: 'features/ordsifjar/src/admin/main.jsx',
				view: 'features/ordsifjar/src/view/main.jsx',
			},
			output: {
				entryFileNames: '[name].js',
				chunkFileNames: 'chunks/[name]-[hash].js',
				assetFileNames: '[name][extname]',
			},
		},
	},
} );
