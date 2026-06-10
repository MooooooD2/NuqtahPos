import { Component, type ErrorInfo, type ReactNode } from 'react'

interface Props {
  children: ReactNode
  fallback?: ReactNode
}
interface State {
  hasError: boolean
  error: Error | null
}

export default class ErrorBoundary extends Component<Props, State> {
  state: State = { hasError: false, error: null }

  static getDerivedStateFromError(error: Error): State {
    return { hasError: true, error }
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    console.error('[ErrorBoundary]', error, info.componentStack)
  }

  render() {
    if (this.state.hasError) {
      if (this.props.fallback) return this.props.fallback
      return (
        <div className="flex flex-col items-center justify-center min-h-[40vh] p-8 text-center">
          <p className="text-lg font-semibold text-gray-800 dark:text-white mb-2">حدث خطأ غير متوقع</p>
          <p className="text-sm text-gray-500 dark:text-gray-400 mb-4">An unexpected error occurred</p>
          <pre className="text-xs text-red-500 bg-red-50 dark:bg-red-900/20 rounded p-3 max-w-lg text-start overflow-auto">
            {this.state.error?.message}
          </pre>
          <button
            className="mt-4 btn btn-secondary text-sm"
            onClick={() => this.setState({ hasError: false, error: null })}
          >
            Try again / حاول مجدداً
          </button>
        </div>
      )
    }
    return this.props.children
  }
}
