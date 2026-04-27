import * as ReactDOM from 'react-dom/client'
import './index.css'
import App from './App.tsx'

const showUiError = (message: string) => {
  if (typeof document === 'undefined') return
  const existing = document.getElementById('pixact-calculator-ui-error')
  if (existing) return

  const box = document.createElement('div')
  box.id = 'pixact-calculator-ui-error'
  box.style.cssText = [
    'margin:16px 0',
    'padding:14px 16px',
    'border-radius:10px',
    'border:1px solid #b91c1c',
    'background:#fef2f2',
    'color:#7f1d1d',
    'font-family:Arial,sans-serif',
    'font-size:14px',
    'line-height:1.45',
  ].join(';')
  box.textContent = `Pixact Calculator Error: ${message}`

  const root = document.getElementById('pixact-calculator-root')
  if (root && root.parentElement) {
    root.parentElement.insertBefore(box, root)
    return
  }
  document.body.prepend(box)
}

const root = document.getElementById('pixact-calculator-root')

if (root) {
  try {
    ReactDOM.createRoot(root).render(<App />)
    window.setTimeout(() => {
      if (!root.hasChildNodes()) {
        showUiError('React mounted, but no UI content was rendered.')
      }
    }, 1200)
  } catch (error) {
    showUiError(error instanceof Error ? error.message : 'Unknown rendering error.')
  }
} else {
  showUiError("Mount element '#pixact-calculator-root' was not found.")
}
