import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  
  // Path aliases
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
      '@components': path.resolve(__dirname, './src/components'),
      '@pages': path.resolve(__dirname, './src/pages'),
      '@hooks': path.resolve(__dirname, './src/hooks'),
      '@utils': path.resolve(__dirname, './src/utils'),
      '@types': path.resolve(__dirname, './src/types'),
      '@api': path.resolve(__dirname, './src/api'),
    },
  },
  
  // Server configuration for Docker development
  server: {
    host: '0.0.0.0',
    port: 3000,
    strictPort: true,
    // Forces the browser to look at exactly this address for the reload signal
    hmr: {
      protocol: 'ws',
      host: 'localhost',
      port: 3000,
      clientPort: 3000,
    },
    // Tells Vite exactly where the "Parent" is
    origin: 'http://localhost:3000',
    // STOPS the infinite "File Changed" detection
    watch: {
      usePolling: true,
      interval: 1000, // Check every 1 second instead of every 100ms
      ignored: [
        '**/node_modules/**', 
        '**/dist/**', 
        '**/.git/**', 
        '**/backend/**', // CRITICAL: Stop watching the Laravel folder!
        '**/storage/**', 
        '**/logs/**'
      ],
    },
    proxy: {
      '/api': {
        target: 'http://nginx:80',
        changeOrigin: true,
        secure: false,
      },
    },
  },

  
  // Build optimization
  build: {
    target: 'es2020',
    outDir: 'dist',
    sourcemap: true,
    
    rollupOptions: {
      output: {
        manualChunks: {
          'react-vendor': ['react', 'react-dom', 'react-router-dom'],
          'query-vendor': ['@tanstack/react-query'],
          'ui-vendor': ['@headlessui/react', 'lucide-react'],
          'chart-vendor': ['recharts'],
        },
      },
    },
    chunkSizeWarningLimit: 1000,
  },
  
  preview: {
    port: 3000,
    host: true,
  },
});
