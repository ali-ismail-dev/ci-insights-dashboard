# Step 4: Frontend Dashboard (React + TypeScript) - Testing Guide

## Overview
This guide verifies the React + TypeScript frontend with Tailwind CSS, React Query, and React Router. All tests must pass before deployment.

---

## Prerequisites

- Step 1-3 completed (Backend running with test data)
- Node.js 18+ installed
- npm 9+ installed
- Backend API accessible at http://localhost:8080

---

## Setup Steps

### 1. Create Frontend Directory Structure

```bash
# Navigate to project root
cd ci-insights-dashboard

# Create frontend directory
mkdir -p frontend/src/{api,components,hooks,pages,types,utils,layouts}
```

### 2. Initialize Frontend Project

```bash
cd frontend

# Copy package.json (from artifact)
# Create package.json with dependencies listed in artifact

# Install dependencies
docker-compose exec app npm install

# This will install:
# - React 18.2
# - TypeScript 5.3
# - Vite 5.0
# - Tailwind CSS 3.4
# - React Query (TanStack Query) 5.18
# - React Router 6.22
# - Axios 1.6
# - And all dev dependencies
```

**Expected Duration:** 2-5 minutes

**Expected Result:**
✅ `node_modules/` directory created
✅ `package-lock.json` generated
✅ No dependency conflicts

---

### 3. Copy Configuration Files

```bash
# Copy files from artifacts:
# - vite.config.ts
# - tailwind.config.js
# - tsconfig.json
# - postcss.config.js (create this)

# Create postcss.config.js
cat > postcss.config.js << 'EOF'
export default {
  plugins: {
    tailwindcss: {},
    autoprefixer: {},
  },
}
EOF
```

---

### 4. Create Source Files

```bash
# Copy from artifacts:
# - src/types/index.ts
# - src/api/client.ts
# - src/hooks/useApi.ts
# - src/App.tsx
# - src/pages/Dashboard.tsx

# Create index.html
cat > index.html << 'EOF'
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <link rel="icon" type="image/svg+xml" href="/vite.svg" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CI Insights Dashboard</title>
  </head>
  <body>
    <div id="root"></div>
    <script type="module" src="/src/main.tsx"></script>
  </body>
</html>
EOF

# Create main.tsx
cat > src/main.tsx << 'EOF'
import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './App'
import './index.css'

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
)
EOF

# Create global CSS
cat > src/index.css << 'EOF'
@tailwind base;
@tailwind components;
@tailwind utilities;

@layer base {
  body {
    @apply bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100;
  }
}
EOF
```

---

## Test Execution Steps

### Test 1: Verify TypeScript Configuration

**Action:** Check TypeScript compilation

```bash
# Run type check
npm run type-check
```

**Expected Results:**
✅ No TypeScript errors
✅ All types resolved correctly
✅ Path aliases working

**Expected Output:**
```
No TypeScript errors found
```

---

### Test 2: Verify Tailwind CSS Setup

**Action:** Build CSS and check output

```bash
# Build project (includes CSS)
npm run build
```

**Expected Results:**
✅ Build completes successfully
✅ CSS file generated in `dist/assets/`
✅ Tailwind utilities included

**Expected Output:**
```
✓ 123 modules transformed.
dist/index.html                   0.45 kB │ gzip: 0.29 kB
dist/assets/index-abc123.css     12.34 kB │ gzip: 3.45 kB
dist/assets/index-def456.js     234.56 kB │ gzip: 78.90 kB
✓ built in 1.23s
```

---

### Test 3: Start Development Server

**Action:** Run dev server

```bash
# Start Vite dev server
npm run dev
```

**Expected Results:**
✅ Server starts on port 3000
✅ No compilation errors
✅ HMR working

**Expected Output:**
```
  VITE v5.0.11  ready in 432 ms

  ➜  Local:   http://localhost:3000/
  ➜  Network: http://192.168.1.x:3000/
  ➜  press h to show help
```

**Keep this running for subsequent tests!**

---

### Test 4: Verify Homepage Loads

**Action:** Open browser and navigate to dashboard

```bash
# Open browser
open http://localhost:3000
```

**Expected Results:**
✅ Page loads without errors
✅ Dashboard component renders
✅ Tailwind styles applied
✅ No console errors

**Visual Checks:**
- Header with "Dashboard" title visible
- Stat cards displayed (even if loading)
- Layout properly styled with Tailwind

---

### Test 5: Verify API Connection

**Action:** Check if frontend connects to backend API

**Prerequisite:** Backend running on port 8080 with test data

```bash
# In backend terminal, verify it's running
docker-compose ps | grep app
# Should show "Up"

# Check API health from frontend
curl http://localhost:8080/api/health
# Should return: {"status":"healthy"}
```

**Browser Check:**
1. Open browser DevTools (F12)
2. Go to Network tab
3. Refresh http://localhost:3000
4. Look for API requests to `/api/dashboard/stats`

**Expected Results:**
✅ API request sent to `http://localhost:8080/api/dashboard/stats`
✅ Response status: 200 OK
✅ Response body contains stats data
✅ No CORS errors

---

### Test 6: Verify React Query Integration

**Action:** Check React Query DevTools

**Browser Steps:**
1. Open http://localhost:3000
2. Look for React Query DevTools icon (bottom-right corner)
3. Click to expand
4. Observe queries

**Expected Results:**
✅ DevTools visible (dev mode only)
✅ Queries listed: `['dashboard-stats']`, `['repositories']`
✅ Query status: `success` (green)
✅ Cache working (queries not refetching constantly)

**Console Check:**
```javascript
// In browser console
window.localStorage.getItem('REACT_QUERY_DEVTOOLS_POSITION')
// Should return position if devtools used
```

---

### Test 7: Verify React Router Navigation

**Action:** Test navigation between routes

**Browser Steps:**
1. Click "Repositories" link (if visible)
2. URL should change to `/repositories`
3. Press browser back button
4. Should navigate back to `/`

**Expected Results:**
✅ URL updates correctly
✅ Components render for each route
✅ Browser back/forward works
✅ No full-page reloads (SPA behavior)

---

### Test 8: Test Loading States

**Action:** Simulate slow network and check loading UI

**Browser Steps:**
1. Open DevTools → Network tab
2. Set throttling to "Slow 3G"
3. Refresh page
4. Observe loading state

**Expected Results:**
✅ Loading skeletons/spinners show
✅ No content flash
✅ Smooth transition to loaded state

---

### Test 9: Test Error Handling

**Action:** Simulate API error

**Backend Action:**
```bash
# Stop backend temporarily
docker-compose stop app nginx
```

**Browser Action:**
1. Refresh http://localhost:3000
2. Wait for API request to fail

**Expected Results:**
✅ Error toast notification appears
✅ User-friendly error message
✅ Page doesn't crash
✅ Retry button (if implemented)

**Cleanup:**
```bash
# Restart backend
docker-compose start app nginx
```

---

### Test 10: Test Responsive Design

**Action:** Check mobile responsiveness

**Browser Steps:**
1. Open DevTools (F12)
2. Toggle device toolbar (Ctrl+Shift+M)
3. Test different viewports:
   - iPhone 12 (390x844)
   - iPad (768x1024)
   - Desktop (1920x1080)

**Expected Results:**
✅ Layout adapts to screen size
✅ Sidebar collapses on mobile
✅ Tables scroll horizontally on mobile
✅ Touch targets ≥ 44x44px
✅ No horizontal scroll

---

### Test 11: Test Dark Mode (if implemented)

**Action:** Toggle dark mode

**Browser Steps:**
1. Look for dark mode toggle
2. Click to switch
3. Observe color changes

**Expected Results:**
✅ Dark mode styles applied
✅ All text readable
✅ Proper contrast ratios
✅ Preference saved (localStorage)

---

### Test 12: Test TypeScript Type Safety

**Action:** Intentionally introduce type error

```typescript
// In src/pages/Dashboard.tsx, temporarily add:
const test: string = 123; // Type error
```

**Run Type Check:**
```bash
npm run type-check
```

**Expected Results:**
✅ TypeScript error caught
✅ Error message clear and helpful
✅ VSCode shows red squiggles

**Expected Error:**
```
src/pages/Dashboard.tsx:10:7 - error TS2322: Type 'number' is not assignable to type 'string'.
```

**Cleanup:** Remove test code

---

### Test 13: Test Hot Module Replacement (HMR)

**Action:** Edit component and observe HMR

**Steps:**
1. Open `src/pages/Dashboard.tsx` in editor
2. Change title text: `"Dashboard"` → `"Dashboard Updated"`
3. Save file
4. Observe browser (should auto-update without full reload)

**Expected Results:**
✅ Browser updates in < 1 second
✅ No full page reload
✅ State preserved (if any)
✅ Console shows HMR update message

---

### Test 14: Test Bundle Size

**Action:** Check production build size

```bash
# Build for production
npm run build

# Check bundle sizes
ls -lh dist/assets/
```

**Expected Results:**
✅ Total bundle < 500KB gzipped
✅ Main JS bundle < 300KB gzipped
✅ CSS bundle < 50KB gzipped
✅ Code splitting working (multiple JS files)

**Expected Output:**
```
-rw-r--r--  1 user  staff   234K Jan 23 10:00 index-abc123.js
-rw-r--r--  1 user  staff    78K Jan 23 10:00 index-abc123.js.gz
-rw-r--r--  1 user  staff    12K Jan 23 10:00 index-def456.css
```

---

### Test 15: Test Production Build

**Action:** Preview production build

```bash
# Build and preview
npm run build
npm run preview
```

**Expected Results:**
✅ Build succeeds
✅ Preview server starts on port 3000
✅ Page loads correctly
✅ All features work in production mode

**Browser Check:**
- Open http://localhost:3000
- Navigate through app
- Check DevTools Console (should be clean)

---

### Test 16: Test Accessibility

**Action:** Run accessibility audit

**Browser Steps:**
1. Open http://localhost:3000
2. Open DevTools → Lighthouse
3. Run audit (Accessibility only)
4. Review score

**Expected Results:**
✅ Accessibility score ≥ 90
✅ No critical issues
✅ Proper ARIA labels
✅ Keyboard navigation works

**Manual Keyboard Test:**
- Press Tab key repeatedly
- Focus indicators visible
- Can navigate entire page without mouse

---

### Test 17: Test React Query Caching

**Action:** Verify queries cache correctly

**Browser Steps:**
1. Load dashboard (initial fetch)
2. Navigate to another route
3. Navigate back to dashboard
4. Observe network tab

**Expected Results:**
✅ Initial load fetches from API
✅ Second visit uses cache (no API call)
✅ Background refetch after staleTime
✅ React Query DevTools shows cached data

---

### Test 18: Test Real Data Integration

**Action:** Verify real backend data displays correctly

**Prerequisites:** Backend has test data from Step 3

```bash
# Backend: Verify test data exists
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "SELECT COUNT(*) FROM pull_requests;"
# Should return count > 0
```

**Browser Check:**
1. Refresh dashboard
2. Check if stats show real numbers
3. Click on a PR (if visible)
4. Verify PR details load

**Expected Results:**
✅ Stats show actual numbers (not hardcoded)
✅ PR list displays (if data exists)
✅ Navigation to PR detail works
✅ Data updates when backend changes

---

## Troubleshooting

### Issue: "Cannot find module" errors

**Solution:**
```bash
# Clear node_modules and reinstall
rm -rf node_modules package-lock.json
npm install
```

### Issue: CORS errors

**Solution:**
```typescript
// Verify vite.config.ts proxy is configured:
server: {
  proxy: {
    '/api': {
      target: 'http://localhost:8080',
      changeOrigin: true,
    },
  },
}
```

### Issue: Tailwind classes not applying

**Solution:**
```bash
# Verify tailwind.config.js content paths
# Should include: "./src/**/*.{js,ts,jsx,tsx}"

# Clear cache and rebuild
rm -rf node_modules/.vite
npm run dev
```

### Issue: TypeScript errors on path aliases

**Solution:**
```json
// Verify tsconfig.json paths match vite.config.ts aliases
"paths": {
  "@/*": ["./src/*"],
  "@components/*": ["./src/components/*"],
  // ... etc
}
```

---

## Success Criteria Checklist

Before proceeding to deployment, verify ALL of the following:

- [ ] TypeScript compiles without errors
- [ ] Dev server starts successfully
- [ ] Production build completes
- [ ] Homepage loads correctly
- [ ] API requests reach backend
- [ ] React Query caching works
- [ ] React Router navigation works
- [ ] Loading states display correctly
- [ ] Error handling works (toast notifications)
- [ ] Responsive design works (mobile/tablet/desktop)
- [ ] HMR updates in < 1 second
- [ ] Bundle size < 500KB gzipped
- [ ] Accessibility score ≥ 90
- [ ] Keyboard navigation works
- [ ] Real backend data displays
- [ ] No console errors
- [ ] No console warnings (except dev-only)
- [ ] Dark mode works (if implemented)

---

## Performance Benchmarks

Expected performance metrics:

| Metric | Target | How to Measure |
|--------|--------|----------------|
| Initial load time | < 2s | Lighthouse Performance score |
| Time to Interactive (TTI) | < 3s | Lighthouse |
| First Contentful Paint (FCP) | < 1s | Lighthouse |
| Largest Contentful Paint (LCP) | < 2.5s | Lighthouse |
| Cumulative Layout Shift (CLS) | < 0.1 | Lighthouse |
| Bundle size (gzipped) | < 500KB | `npm run build` output |
| HMR update time | < 200ms | Manual observation |

---

## Lighthouse Audit

**Run Full Audit:**
```bash
# In browser
1. Open DevTools
2. Go to Lighthouse tab
3. Select: Performance, Accessibility, Best Practices, SEO
4. Click "Analyze page load"
```

**Target Scores:**
- Performance: ≥ 90
- Accessibility: ≥ 90
- Best Practices: ≥ 90
- SEO: ≥ 90

---

**Testing completed on:** [Date]  
**Tested by:** [Your Name]  
**Browser:** [Chrome/Firefox/Safari version]  
**Status:** ✅ PASS / ❌ FAIL  
**Notes:** [Any observations or issues encountered]