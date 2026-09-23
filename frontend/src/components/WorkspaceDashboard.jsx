import { Link } from 'react-router-dom'
import BrandMark from './BrandMark'

const iconPaths = {
  dashboard: (
    <>
      <rect x="3" y="3" width="7" height="7" rx="2" />
      <rect x="14" y="3" width="7" height="7" rx="2" />
      <rect x="3" y="14" width="7" height="7" rx="2" />
      <rect x="14" y="14" width="7" height="7" rx="2" />
    </>
  ),
  people: (
    <>
      <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
      <circle cx="9" cy="7" r="4" />
      <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
    </>
  ),
  shield: <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Zm-3-10 2 2 4-4" />,
  payroll: (
    <>
      <rect x="3" y="5" width="18" height="14" rx="3" />
      <path d="M3 10h18M8 15h2" />
    </>
  ),
  payslip: (
    <>
      <path d="M6 2h9l5 5v15H6z" />
      <path d="M14 2v6h6M9 13h6M9 17h6" />
    </>
  ),
  calendar: (
    <>
      <rect x="3" y="5" width="18" height="16" rx="3" />
      <path d="M16 3v4M8 3v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01" />
    </>
  ),
  clock: (
    <>
      <circle cx="12" cy="12" r="9" />
      <path d="M12 7v5l3 2" />
    </>
  ),
  document: (
    <>
      <path d="M6 2h9l5 5v15H6z" />
      <path d="M14 2v6h6M9 13h6M9 17h4" />
    </>
  ),
  heart: <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78Z" />,
  onboarding: (
    <>
      <circle cx="9" cy="7" r="4" />
      <path d="M3 21v-2a6 6 0 0 1 12 0v2M19 8v6M16 11h6" />
    </>
  ),
  progress: (
    <>
      <path d="M4 19V9M10 19V5M16 19v-7M22 19H2" />
      <path d="m4 7 6-4 6 6 5-5" />
    </>
  ),
  team: (
    <>
      <circle cx="12" cy="8" r="4" />
      <path d="M4 21a8 8 0 0 1 16 0M4 7H2M22 7h-2" />
    </>
  ),
  arrow: <path d="M5 12h14m-5-5 5 5-5 5" />,
  check: <path d="m5 12 4 4L19 6" />,
  lock: (
    <>
      <rect x="4" y="10" width="16" height="11" rx="3" />
      <path d="M8 10V7a4 4 0 0 1 8 0v3" />
    </>
  ),
  logout: (
    <>
      <path d="M10 17l5-5-5-5M15 12H3M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
    </>
  ),
}

export function DashboardIcon({ name, className = '' }) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.8"
      strokeLinecap="round"
      strokeLinejoin="round"
      className={className}
      aria-hidden="true"
    >
      {iconPaths[name] || iconPaths.dashboard}
    </svg>
  )
}

function WorkspaceLink({ module, className = '' }) {
  const content = (
    <>
      <span className="module-card__icon">
        <DashboardIcon name={module.icon} />
      </span>
      <span className="module-card__copy">
        <span className={`availability-pill${module.availability === 'Available' ? '' : ' availability-pill--planned'}`}>
          {module.availability === 'Available' ? 'Connected' : 'Planned'}
        </span>
        <strong>{module.title}</strong>
        <small>{module.description}</small>
      </span>
      <span className="module-card__arrow">
        <DashboardIcon name="arrow" />
      </span>
    </>
  )

  const linkClass = `module-card${module.href ? ' module-card--link' : ''} ${className}`.trim()

  if (module.internal && module.href) {
    return <Link to={module.href} className={linkClass}>{content}</Link>
  }

  if (module.href) {
    return <a href={module.href} target="_blank" rel="noreferrer" className={linkClass}>{content}</a>
  }

  return <div className={linkClass}>{content}</div>
}

function WorkspaceDashboard({
  user,
  sessionStatus,
  title,
  summary,
  statusLabel,
  modules,
  onSignOut,
}) {
  const availableModules = modules.filter((module) => module.availability === 'Available')
  const navigationModules = availableModules.filter((module) => module.internal && module.href).slice(0, 7)
  const readiness = modules.length ? Math.round((availableModules.length / modules.length) * 100) : 0
  const sessionLabel = sessionStatus.loading ? 'Checking' : sessionStatus.ok ? 'Verified' : 'Pending'
  const displayName = user.username || 'Team member'

  const stats = [
    { label: 'Access', value: sessionLabel, icon: 'shield', tone: 'blue' },
    { label: 'Role', value: user.role_name || 'Not assigned', icon: 'people', tone: 'green' },
    { label: 'Connected tools', value: `${availableModules.length} of ${modules.length}`, icon: 'dashboard', tone: 'pink' },
    {
      label: user.role_name === 'Employee' ? 'Employee ID' : 'User ID',
      value: user.role_name === 'Employee' ? (user.employee_id || '—') : (user.user_id || '—'),
      icon: 'lock',
      tone: 'blue',
    },
  ]

  return (
    <main className="workspace-app">
      <aside className="workspace-sidebar">
        <div className="workspace-sidebar__brand">
          <BrandMark />
        </div>

        <nav className="workspace-nav" aria-label="Main navigation">
          <p className="workspace-nav__label">Workspace</p>
          <Link to="/dashboard" className="workspace-nav__item workspace-nav__item--active">
            <DashboardIcon name="dashboard" />
            <span>Dashboard</span>
          </Link>
          {navigationModules.map((module) => (
            <Link key={module.title} to={module.href} className="workspace-nav__item">
              <DashboardIcon name={module.icon} />
              <span>{module.title}</span>
            </Link>
          ))}
        </nav>

        <div className="workspace-sidebar__footer">
          <div className="sidebar-profile">
            <span className="profile-avatar">{displayName.slice(0, 1).toUpperCase()}</span>
            <span>
              <strong>{displayName}</strong>
              <small>{user.role_name}</small>
            </span>
          </div>
          <button type="button" className="sidebar-signout" onClick={onSignOut}>
            <DashboardIcon name="logout" />
            <span>Sign out</span>
          </button>
        </div>
      </aside>

      <section className="workspace-main">
        <header className="workspace-topbar">
          <div className="workspace-mobile-brand"><BrandMark /></div>
          <div>
            <p className="workspace-topbar__eyebrow">Good to see you, {displayName}</p>
            <h1>{title}</h1>
          </div>
          <div className="workspace-topbar__actions">
            <span className={`session-chip${sessionStatus.ok ? ' session-chip--ok' : ''}`}>
              <span className="session-chip__dot" />
              {sessionLabel}
            </span>
            <button type="button" className="topbar-signout" onClick={onSignOut}>
              <DashboardIcon name="logout" />
              <span>Sign out</span>
            </button>
          </div>
        </header>

        <div className="workspace-content">
          <section className="workspace-hero">
            <div>
              <span className="workspace-hero__label">{statusLabel}</span>
              <h2>Everything you need, in one clear workspace.</h2>
              <p>{summary}</p>
            </div>
            <div className="workspace-hero__art" aria-hidden="true">
              <span className="hero-orbit hero-orbit--one" />
              <span className="hero-orbit hero-orbit--two" />
              <span className="hero-person hero-person--one" />
              <span className="hero-person hero-person--two" />
              <span className="hero-person hero-person--three" />
            </div>
          </section>

          <section className="workspace-stats" aria-label="Workspace summary">
            {stats.map((stat) => (
              <article key={stat.label} className={`stat-card stat-card--${stat.tone}`}>
                <span className="stat-card__icon"><DashboardIcon name={stat.icon} /></span>
                <span>
                  <small>{stat.label}</small>
                  <strong>{stat.value}</strong>
                </span>
              </article>
            ))}
          </section>

          <div className="workspace-grid">
            <section className="workspace-panel workspace-panel--modules">
              <div className="panel-heading">
                <div>
                  <span className="panel-heading__eyebrow">Your tools</span>
                  <h2>Workspace shortcuts</h2>
                </div>
                <span className="panel-heading__count">{modules.length} modules</span>
              </div>
              <div className="module-grid">
                {modules.map((module) => <WorkspaceLink key={module.title} module={module} />)}
              </div>
            </section>

            <aside className="workspace-rail">
              <section className="workspace-panel readiness-panel">
                <div className="panel-heading panel-heading--compact">
                  <div>
                    <span className="panel-heading__eyebrow">System readiness</span>
                    <h2>Connected tools</h2>
                  </div>
                </div>
                <div className="readiness-visual">
                  <div className="readiness-ring" style={{ '--readiness': `${readiness * 3.6}deg` }}>
                    <span><strong>{readiness}%</strong><small>ready</small></span>
                  </div>
                  <p>{availableModules.length} of {modules.length} tools are connected to an available screen or backend check.</p>
                </div>
              </section>

              <section className="workspace-panel account-panel">
                <div className="panel-heading panel-heading--compact">
                  <div>
                    <span className="panel-heading__eyebrow">Account</span>
                    <h2>Access details</h2>
                  </div>
                </div>
                <dl className="account-list">
                  <div><dt>Username</dt><dd>{user.username || 'Unavailable'}</dd></div>
                  <div><dt>Email</dt><dd>{user.email || 'Unavailable'}</dd></div>
                  <div><dt>Role</dt><dd>{user.role_name || 'Unavailable'}</dd></div>
                </dl>
                <div className={`access-note${sessionStatus.ok ? ' access-note--ok' : ''}`} aria-live="polite">
                  <DashboardIcon name={sessionStatus.ok ? 'check' : 'lock'} />
                  <span>{sessionStatus.loading ? 'Checking the current secure session…' : (sessionStatus.message || 'Session response unavailable.')}</span>
                </div>
              </section>
            </aside>
          </div>
        </div>
      </section>
    </main>
  )
}

export default WorkspaceDashboard
