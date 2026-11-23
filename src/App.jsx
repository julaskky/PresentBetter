import AdminAssignments from './components/AdminAssignments';
import { useEffect, useState } from 'react';
import {
  ArrowRight, Presentation, ShieldCheck, Clock3, MessageSquareText,
  LogOut, UserRound, Sparkles, CheckCircle2, Menu, X,
  LayoutDashboard, FileText, UploadCloud, Activity, Plus,
  Download, RefreshCw, CircleCheck, Clock, MessageCircle,
  FileCheck2, AlertCircle,Users
} from 'lucide-react';
import { api, bootstrap } from './services/api';

const blank = {
  fullName: '',
  email: '',
  password: '',
  confirmPassword: '',
};

function Brand() {
  return (
    <a className="brand" href="#">
      <span className="brand-mark"><Presentation size={21} /></span>
      <span>present<span className="brand-accent">better</span></span>
    </a>
  );
}

export default function App() {
  const [user, setUser] = useState(null);
  const [screen, setScreen] = useState('login');
  const [form, setForm] = useState(blank);
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    (async () => {
      try {
        await bootstrap();
        const result = await api.me();
        setUser(result.user);
      } catch {
        // Visitors who are not signed in should see the login screen.
      }
    })();
  }, []);

  const update = (key) => (event) => {
    setForm((previous) => ({
      ...previous,
      [key]: event.target.value,
    }));
  };

  const changeScreen = (next) => {
    setScreen(next);
    setError('');
    setNotice('');
    setForm(blank);
  };

  async function submit(event) {
    event.preventDefault();
    setError('');
    setNotice('');

    if (
      screen === 'register' &&
      form.password !== form.confirmPassword
    ) {
      setError('Your passwords do not match.');
      return;
    }

    setBusy(true);

    try {
      await bootstrap();

      if (screen === 'register') {
        await api.register({
          fullName: form.fullName.trim(),
          email: form.email.trim(),
          password: form.password,
        });

        changeScreen('login');
        setNotice('Your account is ready. Sign in to continue.');
      } else {
        const result = await api.login({
          email: form.email.trim(),
          password: form.password,
        });

        setUser(result.user);
      }
    } catch (e) {
      setError(e.message || 'Something went wrong. Please try again.');
    } finally {
      setBusy(false);
    }
  }

  async function logout() {
    try {
      await bootstrap();
      await api.logout();
    } catch {
      // Clear the local view even if the network request fails.
    }

    setUser(null);
    changeScreen('login');
  }

  if (user) {
    return <Dashboard user={user} onLogout={logout} />;
  }

  return (
    <main className="auth-shell">
      <header className="topbar">
        <Brand />
        <div className="topbar-right">
          <span className="topbar-note">
            Your next great presentation starts here.
          </span>
          <span className="secure-label">
            <ShieldCheck size={15} /> Private workspace
          </span>
        </div>
      </header>

      <section className="auth-layout">
        <div className="story-panel">
          <div className="eyebrow">
            <Sparkles size={14} /> YOUR IDEAS DESERVE THE SPOTLIGHT
          </div>

          <h1>Make your next<br />presentation <em>matter.</em></h1>

          <p className="story-copy">
            Thoughtful feedback. Clear direction. A more confident you.
            Get expert support to turn your ideas into presentations
            people remember.
          </p>

          <div className="benefit-list">
            <div>
              <span className="benefit-icon">
                <MessageSquareText size={19} />
              </span>
              <span>
                <b>Feedback that moves you forward</b>
                <small>Practical advice, built around your goals.</small>
              </span>
            </div>

            <div>
              <span className="benefit-icon"><Clock3 size={19} /></span>
              <span>
                <b>Support on your schedule</b>
                <small>Prepare at your pace and plan a session.</small>
              </span>
            </div>

            <div>
              <span className="benefit-icon"><ShieldCheck size={19} /></span>
              <span>
                <b>Your work, treated with care</b>
                <small>A private space for your presentation journey.</small>
              </span>
            </div>
          </div>

          <div className="quote-card">
            <div className="quote-symbol">“</div>
            <p>Great presentations aren't born. They're shaped.</p>
            <span>YOUR IDEAS. YOUR MOMENT.</span>
          </div>

          <div className="decor-orb orb-one" />
          <div className="decor-orb orb-two" />
        </div>

        <div className="form-panel">
          <div className="form-wrap">
            <div className="mobile-brand"><Brand /></div>

            <div className="form-kicker">
              {screen === 'login' ? 'WELCOME BACK' : 'YOUR PRESENTATION JOURNEY'}
            </div>

            <h2>
              {screen === 'login'
                ? 'Sign in to your space'
                : 'Create your account'}
            </h2>

            <p className="form-subtitle">
              {screen === 'login'
                ? 'Pick up where your ideas left off.'
                : 'Let’s make your next presentation your best one.'}
            </p>

            <div className="tab-switch" role="tablist" aria-label="Account access">
              <button
                type="button"
                role="tab"
                aria-selected={screen === 'login'}
                className={screen === 'login' ? 'active' : ''}
                onClick={() => changeScreen('login')}
              >
                Sign in
              </button>

              <button
                type="button"
                role="tab"
                aria-selected={screen === 'register'}
                className={screen === 'register' ? 'active' : ''}
                onClick={() => changeScreen('register')}
              >
                Create account
              </button>
            </div>

            {notice && (
              <div className="notice success">
                <CheckCircle2 size={17} /> {notice}
              </div>
            )}

            {error && (
              <div className="notice error" role="alert">{error}</div>
            )}

            <form onSubmit={submit} className="auth-form">
              {screen === 'register' && (
                <label>
                  Full name
                  <div className="input-wrap">
                    <UserRound size={17} />
                    <input
                      autoComplete="name"
                      value={form.fullName}
                      onChange={update('fullName')}
                      placeholder="Your name"
                      required
                      minLength={2}
                      maxLength={120}
                    />
                  </div>
                </label>
              )}

              <label>
                Email address
                <div className="input-wrap">
                  <span className="input-at">@</span>
                  <input
                    type="email"
                    autoComplete="email"
                    value={form.email}
                    onChange={update('email')}
                    placeholder="you@example.com"
                    required
                    maxLength={254}
                  />
                </div>
              </label>

              <label>
                Password
                <div className="input-wrap">
                  <ShieldCheck size={17} />
                  <input
                    type="password"
                    autoComplete={
                      screen === 'login' ? 'current-password' : 'new-password'
                    }
                    value={form.password}
                    onChange={update('password')}
                    placeholder={
                      screen === 'register'
                        ? 'At least 12 characters'
                        : 'Enter your password'
                    }
                    required
                    minLength={screen === 'register' ? 12 : 1}
                    maxLength={128}
                  />
                </div>
                {screen === 'register' && (
                  <small className="field-hint">
                    Use 12 or more characters.
                  </small>
                )}
              </label>

              {screen === 'register' && (
                <label>
                  Confirm password
                  <div className="input-wrap">
                    <ShieldCheck size={17} />
                    <input
                      type="password"
                      autoComplete="new-password"
                      value={form.confirmPassword}
                      onChange={update('confirmPassword')}
                      placeholder="Enter password again"
                      required
                      minLength={12}
                      maxLength={128}
                    />
                  </div>
                </label>
              )}

              <button className="primary-btn" disabled={busy}>
                {busy
                  ? 'Please wait…'
                  : screen === 'login'
                    ? 'Sign in securely'
                    : 'Create my account'}
                <ArrowRight size={17} />
              </button>
            </form>

            <p className="form-foot">
              <ShieldCheck size={14} />
              Your account is protected with secure authentication.
            </p>

            <p className="terms-note">
              By continuing, you agree to use the platform responsibly.
              Service terms and privacy information should be published
              before public launch.
            </p>
          </div>
        </div>
      </section>

      <footer className="page-footer">
        <span>© {new Date().getFullYear()} PresentBetter</span>
        <span>Prepare with purpose. Present with confidence.</span>
      </footer>
    </main>
  );
}

function Dashboard({ user, onLogout }) {
  const [page, setPage] = useState('overview');
  const [overview, setOverview] = useState(null);
  const [presentations, setPresentations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [showUpload, setShowUpload] = useState(false);
  const [uploadBusy, setUploadBusy] = useState(false);
  const [uploadError, setUploadError] = useState('');
  const [uploadNotice, setUploadNotice] = useState('');
  const [uploadForm, setUploadForm] = useState({
    title: '',
    description: '',
    file: null,
  });
  const [uploadKey, setUploadKey] = useState(0);
  const [navOpen, setNavOpen] = useState(false);

  async function loadData(isRefresh = false) {
    if (isRefresh) setRefreshing(true);
    else setLoading(true);

    try {
      await bootstrap();

      const [overviewResult, presentationResult] = await Promise.all([
        api.dashboardOverview(),
        api.presentations(),
      ]);

      setOverview(overviewResult);
      setPresentations(presentationResult.presentations || []);
      setUploadError('');
    } catch (e) {
      setUploadError(e.message || 'Could not load your workspace.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }

  useEffect(() => {
    loadData();
  }, []);

  async function handleUpload(event) {
    event.preventDefault();
    setUploadError('');
    setUploadNotice('');

    if (!uploadForm.file) {
      setUploadError('Choose a PDF, PPT, or PPTX file first.');
      return;
    }

    const data = new FormData();
    data.append('title', uploadForm.title.trim());
    data.append('description', uploadForm.description.trim());
    data.append('presentation', uploadForm.file);

    setUploadBusy(true);

    try {
      await bootstrap();
      await api.submitPresentation(data);

      setUploadNotice('Your presentation was submitted successfully.');
      setUploadForm({ title: '', description: '', file: null });
      setUploadKey((key) => key + 1);
      setShowUpload(false);
      setPage('presentations');

      await loadData(true);
    } catch (e) {
      setUploadError(e.message || 'Upload failed. Please try again.');
    } finally {
      setUploadBusy(false);
    }
  }

  const navItems = user.role === 'presenter'
  ? [
      { id: 'overview', label: 'Overview', icon: LayoutDashboard },
      { id: 'presentations', label: 'My presentations', icon: FileText },
    ]
  : user.role === 'consultant'
    ? [
        { id: 'overview', label: 'Overview', icon: LayoutDashboard },
        { id: 'presentations', label: 'Assigned presentations', icon: FileText },
      ]
    : [
        { id: 'overview', label: 'Overview', icon: LayoutDashboard },
        { id: 'presentations', label: 'Presentations', icon: FileText },
        { id: 'assignments', label: 'Manage assignments', icon: Users },
      ];

  const stats = overview?.stats || {};

  const statusLabel = (status) => ({
    submitted: 'Submitted',
    under_review: 'Under review',
    feedback_available: 'Feedback available',
    completed: 'Completed',
  }[status] || status);

  const statusClass = (status) => ({
    submitted: 'status-submitted',
    under_review: 'status-review',
    feedback_available: 'status-feedback',
    completed: 'status-completed',
  }[status] || '');

  function formatDate(value) {
    if (!value) return '—';
    const parsed = new Date(value.replace(' ', 'T'));
    return Number.isNaN(parsed.getTime())
      ? value
      : parsed.toLocaleDateString(undefined, {
          day: 'numeric',
          month: 'short',
          year: 'numeric',
        });
  }

  function formatSize(bytes) {
    if (bytes < 1024 * 1024) {
      return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  }

  async function downloadPresentation(item) {
    setUploadError('');

    try {
      await bootstrap();

      const response = await fetch(
        api.presentationDownloadUrl(item.id),
        { credentials: 'include' }
      );

      if (!response.ok) {
        const result = await response.json().catch(() => ({}));
        throw new Error(result.message || 'Download failed.');
      }

      const blob = await response.blob();
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement('a');

      anchor.href = url;
      anchor.download = item.fileName;
      document.body.appendChild(anchor);
      anchor.click();
      anchor.remove();

      setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (e) {
      setUploadError(e.message || 'Download failed.');
    }
  }

  return (
    <main className="dashboard-shell">
      <header className="dash-nav">
        <Brand />

        <div className="dash-nav-right">
          <button
            type="button"
            className="mobile-menu-btn"
            aria-label={navOpen ? 'Close menu' : 'Open menu'}
            onClick={() => setNavOpen((value) => !value)}
          >
            {navOpen ? <X size={20} /> : <Menu size={20} />}
          </button>

          <div className="dash-user">
            <div className="avatar">
              {(user.fullName || 'P').charAt(0).toUpperCase()}
            </div>

            <div className="user-meta">
              <b>{user.fullName}</b>
              <span>{user.role}</span>
            </div>

            <button type="button" className="logout-btn" onClick={onLogout}>
              <LogOut size={17} /><span>Sign out</span>
            </button>
          </div>
        </div>
      </header>

      <div className="workspace-layout">
        <aside className={`workspace-sidebar ${navOpen ? 'is-open' : ''}`}>
          <div className="sidebar-caption">WORKSPACE</div>

          {navItems.map((item) => {
            const Icon = item.icon;

            return (
              <button
                type="button"
                key={item.id}
                className={`sidebar-link ${page === item.id ? 'active' : ''}`}
                onClick={() => {
                  setPage(item.id);
                  setNavOpen(false);
                }}
              >
                <Icon size={18} />
                <span>{item.label}</span>

                {item.id === 'presentations' && presentations.length > 0 && (
                  <span className="sidebar-count">{presentations.length}</span>
                )}
              </button>
            );
          })}

          <div className="sidebar-help">
            <div className="sidebar-help-icon"><Sparkles size={17} /></div>
            <b>Make every slide count.</b>
            <p>
              Keep your ideas moving with thoughtful preparation
              and feedback.
            </p>
          </div>
        </aside>

        <section className="workspace-main">
          {uploadError && !showUpload && (
            <div className="notice error workspace-notice">
              <AlertCircle size={17} />
              <span>{uploadError}</span>
              <button
                type="button"
                onClick={() => setUploadError('')}
                aria-label="Dismiss error"
              >
                <X size={15} />
              </button>
            </div>
          )}

          {uploadNotice && (
            <div className="notice success workspace-notice">
              <CheckCircle2 size={17} />
              <span>{uploadNotice}</span>
              <button
                type="button"
                onClick={() => setUploadNotice('')}
                aria-label="Dismiss notice"
              >
                <X size={15} />
              </button>
            </div>
          )}

          {page === 'overview' && (
            <>
              <div className="workspace-heading-row">
                <div>
                  <div className="eyebrow">
                    <Sparkles size={14} /> YOUR WORKSPACE
                  </div>
                  <h1>Welcome, {user.fullName.split(' ')[0]}.</h1>
                  <p>Make progress on your next great presentation.</p>
                </div>

                <button
                  type="button"
                  className="outline-btn"
                  onClick={() => loadData(true)}
                  disabled={refreshing}
                >
                  <RefreshCw size={16} className={refreshing ? 'spin' : ''} />
                  Refresh
                </button>
              </div>

              {user.role === 'presenter' && (
                <Hero
                  roleLabel="PRESENTER"
                  title={<>Your ideas deserve<br />to be understood.</>}
                  description="Upload a presentation to keep everything in one place and prepare for your next step with confidence."
                  buttonText="Submit a presentation"
                  onAction={() => {
                    setUploadError('');
                    setShowUpload(true);
                  }}
                  buttonIcon={<Plus size={17} />}
                />
              )}

              {user.role === 'consultant' && (
                <Hero
                  roleLabel="CONSULTANT"
                  title={<>Help great ideas<br />make an impact.</>}
                  description="Browse incoming presentation submissions and help presenters strengthen their message."
                  buttonText="Browse submissions"
                  onAction={() => setPage('presentations')}
                  buttonIcon={<FileText size={17} />}
                />
              )}

              {user.role === 'admin' && (
                <Hero
                  roleLabel="ADMIN"
                  title={<>A clearer view<br />of the platform.</>}
                  description="Monitor account and presentation activity from your workspace."
                />
              )}

              {loading ? (
                <div className="loading-panel">
                  <span className="loader" />
                  <p>Loading your workspace…</p>
                </div>
              ) : (
                <div className="stats-grid">
                  {user.role === 'presenter' && (
                    <>
                      <StatCard
                        icon={FileText}
                        label="Total presentations"
                        value={stats.total || 0}
                        tone="blue"
                      />
                      <StatCard
                        icon={Clock}
                        label="Awaiting review"
                        value={(stats.submitted || 0) + (stats.underReview || 0)}
                        tone="amber"
                      />
                      <StatCard
                        icon={MessageCircle}
                        label="Feedback ready"
                        value={stats.feedbackAvailable || 0}
                        tone="green"
                      />
                      <StatCard
                        icon={CircleCheck}
                        label="Completed"
                        value={stats.completed || 0}
                        tone="violet"
                      />
                    </>
                  )}

                  {user.role === 'consultant' && (
                    <>
                      <StatCard icon={FileText} label="All submissions" value={stats.total || 0} tone="blue" />
                      <StatCard icon={Clock} label="New submissions" value={stats.submitted || 0} tone="amber" />
                      <StatCard icon={MessageCircle} label="Feedback ready" value={stats.feedbackAvailable || 0} tone="green" />
                      <StatCard icon={CircleCheck} label="Completed" value={stats.completed || 0} tone="violet" />
                    </>
                  )}

                  {user.role === 'admin' && (
                    <>
                      <StatCard icon={UserRound} label="Active accounts" value={stats.users || 0} tone="blue" />
                      <StatCard icon={UserRound} label="Presenters" value={stats.presenters || 0} tone="amber" />
                      <StatCard icon={ShieldCheck} label="Consultants" value={stats.consultants || 0} tone="green" />
                      <StatCard icon={FileText} label="Presentations" value={stats.presentations || 0} tone="violet" />
                    </>
                  )}
                </div>
              )}

              <div className="overview-lower-grid">
                <section className="panel recent-panel">
                  <div className="panel-heading">
                    <div>
                      <h3>
                        {user.role === 'presenter'
                          ? 'Recent presentations'
                          : 'Latest submissions'}
                      </h3>
                      <p>A quick look at your latest work.</p>
                    </div>

                    <button
                      type="button"
                      className="text-btn"
                      onClick={() => setPage('presentations')}
                    >
                      View all <ArrowRight size={15} />
                    </button>
                  </div>

                  {!loading &&
                  (overview?.recentPresentations || []).length === 0 ? (
                    <EmptyState
                      title="Your workspace starts here"
                      text={
                        user.role === 'presenter'
                          ? 'Submit your first presentation and it will appear here.'
                          : 'There are no presentation submissions yet.'
                      }
                      action={user.role === 'presenter' ? (
                        <button
                          type="button"
                          className="small-primary-btn"
                          onClick={() => setShowUpload(true)}
                        >
                          <Plus size={15} /> Submit presentation
                        </button>
                      ) : null}
                    />
                  ) : (
                    <div className="recent-list">
                      {(overview?.recentPresentations || []).map((item) => (
                        <PresentationRow
                          key={item.id}
                          item={item}
                          userRole={user.role}
                          statusLabel={statusLabel}
                          statusClass={statusClass}
                          formatDate={formatDate}
                          formatSize={formatSize}
                        />
                      ))}
                    </div>
                  )}


          {page === 'assignments' && user.role === 'admin' && (
            <AdminAssignments onChanged={loadData} />
          )}
                </section>

                <section className="panel activity-panel">
                  <div className="panel-heading">
                    <div>
                      <h3>Recent activity</h3>
                      <p>Updates from your workspace.</p>
                    </div>
                    <span className="panel-icon"><Activity size={17} /></span>
                  </div>

                  {(overview?.activity || []).length === 0 ? (
                    <div className="activity-empty">
                      <span><Activity size={18} /></span>
                      <p>
                        {user.role === 'presenter'
                          ? 'Your submissions and updates will show here.'
                          : 'New platform activity will show here.'}
                      </p>
                    </div>
                  ) : (
                    <div className="activity-list">
                      {overview.activity.map((item) => (
                        <div className="activity-item" key={item.id}>
                          <span className="activity-dot" />
                          <div>
                            <p>{item.message}</p>
                            <small>{formatDate(item.createdAt)}</small>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </section>
              </div>
            </>
          )}

          {page === 'presentations' && (
            <section className="presentations-page">
              <div className="workspace-heading-row">
                <div>
                  <div className="eyebrow">
                    <FileText size={14} /> PRESENTATION LIBRARY
                  </div>
                  <h1>
                    {user.role === 'presenter'
                      ? 'My presentations'
                      : 'Presentations'}
                  </h1>
                  <p>
                    {user.role === 'presenter'
                      ? 'Keep your work organised and track each submission.'
                      : 'Browse submissions from presenters.'}
                  </p>
                </div>

                <div className="heading-actions">
                  <button
                    type="button"
                    className="outline-btn"
                    onClick={() => loadData(true)}
                    disabled={refreshing}
                  >
                    <RefreshCw
                      size={16}
                      className={refreshing ? 'spin' : ''}
                    />
                    Refresh
                  </button>

                  {user.role === 'presenter' && (
                    <button
                      type="button"
                      className="primary-action-btn"
                      onClick={() => {
                        setUploadError('');
                        setShowUpload(true);
                      }}
                    >
                      <Plus size={17} /> New submission
                    </button>
                  )}
                </div>
              </div>

              {loading ? (
                <div className="loading-panel">
                  <span className="loader" />
                  <p>Loading presentations…</p>
                </div>
              ) : presentations.length === 0 ? (
                <div className="panel library-empty">
                  <EmptyState
                    title="No presentations yet"
                    text={
                      user.role === 'presenter'
                        ? 'Your presentation library will grow with every submission.'
                        : 'No presenters have submitted work yet.'
                    }
                    action={user.role === 'presenter' ? (
                      <button
                        type="button"
                        className="primary-action-btn"
                        onClick={() => setShowUpload(true)}
                      >
                        <UploadCloud size={17} />
                        Submit your first presentation
                      </button>
                    ) : null}
                  />
                </div>
              ) : (
                <div className="panel library-panel">
                  <div className="library-summary">
                    <div>
                      <b>{presentations.length}</b>
                      <span>
                        {presentations.length === 1
                          ? 'presentation'
                          : 'presentations'}
                      </span>
                    </div>
                    <span>Submitted files are available to authorised users.</span>
                  </div>

                  <div className="library-table-wrap">
                    <table className="library-table">
                      <thead>
                        <tr>
                          <th>Presentation</th>
                          {user.role !== 'presenter' && <th>Presenter</th>}
                          <th>Submitted</th>
                          <th>Status</th>
                          <th>File</th>
                        </tr>
                      </thead>

                      <tbody>
                        {presentations.map((item) => (
                          <tr key={item.id}>
                            <td>
                              <div className="table-title">
                                <span className="file-tile">
                                  <FileText size={18} />
                                </span>
                                <span>
                                  <b>{item.title}</b>
                                  <small>{item.description || item.fileName}</small>
                                </span>
                              </div>
                            </td>

                            {user.role !== 'presenter' && (
                              <td>{item.presenterName || '—'}</td>
                            )}

                            <td>{formatDate(item.submittedAt)}</td>

                            <td>
                              <span className={`status-pill ${statusClass(item.status)}`}>
                                {statusLabel(item.status)}
                              </span>
                            </td>

                            <td>
                              <button
                                type="button"
                                className="download-link"
                                onClick={() => downloadPresentation(item)}
                              >
                                <Download size={15} /> Download
                              </button>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}
            </section>
          )}
        </section>
      </div>

      <footer className="page-footer">
        <span>© {new Date().getFullYear()} PresentBetter</span>
        <span>Secure workspace · Your ideas, supported.</span>
      </footer>

      {showUpload && (
        <div
          className="modal-backdrop"
          role="presentation"
          onMouseDown={(event) => {
            if (
              event.target === event.currentTarget &&
              !uploadBusy
            ) {
              setShowUpload(false);
            }
          }}
        >
          <section
            className="upload-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="upload-title"
          >
            <div className="modal-heading">
              <div>
                <span className="modal-kicker">NEW SUBMISSION</span>
                <h2 id="upload-title">Share your presentation</h2>
                <p>
                  Upload your work so you can keep track of it in one place.
                </p>
              </div>

              <button
                type="button"
                className="icon-btn"
                aria-label="Close dialog"
                disabled={uploadBusy}
                onClick={() => setShowUpload(false)}
              >
                <X size={19} />
              </button>
            </div>

            {uploadError && (
              <div className="notice error">
                <AlertCircle size={17} /> {uploadError}
              </div>
            )}

            <form className="upload-form" onSubmit={handleUpload}>
              <label>
                Presentation title
                <input
                  value={uploadForm.title}
                  onChange={(event) =>
                    setUploadForm((form) => ({
                      ...form,
                      title: event.target.value,
                    }))
                  }
                  placeholder="e.g. Research proposal presentation"
                  required
                  maxLength={180}
                />
              </label>

              <label>
                Description <span className="optional-label">Optional</span>
                <textarea
                  value={uploadForm.description}
                  onChange={(event) =>
                    setUploadForm((form) => ({
                      ...form,
                      description: event.target.value,
                    }))
                  }
                  placeholder="What is this presentation about?"
                  rows={3}
                  maxLength={5000}
                />
              </label>

              <label className="file-drop">
                <UploadCloud size={25} />
                <b>
                  {uploadForm.file
                    ? uploadForm.file.name
                    : 'Choose your presentation file'}
                </b>
                <span>PDF, PPT or PPTX · Maximum 20 MB</span>
                <input
                  key={uploadKey}
                  type="file"
                  accept=".pdf,.ppt,.pptx,application/pdf,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation"
                  onChange={(event) =>
                    setUploadForm((form) => ({
                      ...form,
                      file: event.target.files?.[0] || null,
                    }))
                  }
                  required
                />
              </label>

              <div className="modal-actions">
                <button
                  type="button"
                  className="outline-btn"
                  disabled={uploadBusy}
                  onClick={() => setShowUpload(false)}
                >
                  Cancel
                </button>

                <button className="primary-action-btn" disabled={uploadBusy}>
                  {uploadBusy ? (
                    <>
                      <span className="button-spinner" /> Uploading…
                    </>
                  ) : (
                    <>
                      <UploadCloud size={16} /> Submit presentation
                    </>
                  )}
                </button>
              </div>
            </form>
          </section>
        </div>
      )}
    </main>
  );
}

function Hero({
  roleLabel,
  title,
  description,
  buttonText,
  onAction,
  buttonIcon,
}) {
  return (
    <div className="dashboard-hero">
      <div className="hero-copy">
        <span className="card-eyebrow">PRESENTBETTER · {roleLabel}</span>
        <h2>{title}</h2>
        <p>{description}</p>

        {buttonText && (
          <button type="button" className="hero-action" onClick={onAction}>
            {buttonIcon} {buttonText} <ArrowRight size={16} />
          </button>
        )}
      </div>

      <div className="hero-illustration">
        <div className="hero-glow" />
        <div className="hero-document">
          <div className="doc-top">
            <span /><span /><span />
          </div>
          <div className="doc-icon"><Presentation size={26} /></div>
          <div className="doc-line wide" />
          <div className="doc-line" />
          <div className="doc-chart">
            <span /><span /><span /><span /><span />
          </div>
          <div className="doc-line short" />
        </div>
        <div className="hero-floating">
          <CheckCircle2 size={17} />
          <span>One step closer</span>
        </div>
      </div>
    </div>
  );
}

function StatCard({ icon: Icon, label, value, tone }) {
  return (
    <article className="stat-card">
      <span className={`stat-icon ${tone}`}><Icon size={19} /></span>
      <div>
        <p>{label}</p>
        <strong>{value}</strong>
      </div>
      <span className="stat-decoration" />
    </article>
  );
}

function PresentationRow({
  item,
  userRole,
  statusLabel,
  statusClass,
  formatDate,
  formatSize,
}) {
  return (
    <div className="recent-row">
      <span className="recent-file-icon"><FileText size={18} /></span>
      <div className="recent-row-main">
        <b>{item.title}</b>
        <span>
          {userRole !== 'presenter' && item.presenterName
            ? `${item.presenterName} · `
            : ''}
          {formatSize(item.fileSize)} · {formatDate(item.submittedAt)}
        </span>
      </div>
      <span className={`status-pill ${statusClass(item.status)}`}>
        {statusLabel(item.status)}
      </span>
    </div>
  );
}

function EmptyState({ title, text, action }) {
  return (
    <div className="empty-state">
      <span className="empty-state-icon"><FileCheck2 size={25} /></span>
      <h3>{title}</h3>
      <p>{text}</p>
      {action}
    </div>
  );
}
