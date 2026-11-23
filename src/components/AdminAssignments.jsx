import { useEffect, useMemo, useState } from 'react';
import {
  ClipboardList,
  UserRound,
  FileText,
  RefreshCw,
  UserPlus,
  UserMinus,
  CheckCircle2,
  AlertCircle,
  Clock3,
  Search,
  Users,
} from 'lucide-react';
import { api, bootstrap } from '../services/api';

function getId(item) {
  return String(item?.id ?? '');
}

function getPresentationId(item) {
  return String(item?.presentationId ?? item?.presentation_id ?? '');
}

function getConsultantId(item) {
  return String(item?.consultantId ?? item?.consultant_id ?? '');
}

function dateLabel(value) {
  if (!value) return '—';

  const parsed = new Date(String(value).replace(' ', 'T'));

  return Number.isNaN(parsed.getTime())
    ? value
    : parsed.toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
      });
}

function statusLabel(value) {
  const labels = {
    assigned: 'Assigned',
    in_progress: 'In progress',
    completed: 'Completed',
    draft: 'Draft saved',
    published: 'Published',
    submitted: 'Submitted',
    under_review: 'Under review',
    feedback_available: 'Feedback available',
  };

  return labels[value] || value || 'Not started';
}

export default function AdminAssignments({ onChanged }) {
  const [data, setData] = useState({
    presentations: [],
    consultants: [],
    assignments: [],
  });

  const [presentationId, setPresentationId] = useState('');
  const [consultantId, setConsultantId] = useState('');
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [saving, setSaving] = useState(false);
  const [removingId, setRemovingId] = useState('');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [confirmRemoval, setConfirmRemoval] = useState(null);

  async function loadAssignments(refresh = false) {
    if (refresh) setRefreshing(true);
    else setLoading(true);

    setError('');

    try {
      await bootstrap();

      const result = await api.assignments();

      setData({
        presentations: Array.isArray(result.presentations)
          ? result.presentations
          : [],
        consultants: Array.isArray(result.consultants)
          ? result.consultants
          : [],
        assignments: Array.isArray(result.assignments)
          ? result.assignments
          : [],
      });
    } catch (e) {
      setError(e.message || 'Could not load assignments.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }

  useEffect(() => {
    loadAssignments();
  }, []);

  const assignedPairs = useMemo(
    () =>
      new Set(
        data.assignments.map(
          (item) => `${getPresentationId(item)}:${getConsultantId(item)}`
        )
      ),
    [data.assignments]
  );

  const availableConsultants = useMemo(
    () =>
      data.consultants.filter(
        (item) => item.isActive !== false && item.is_active !== 0
      ),
    [data.consultants]
  );

  const selectedPresentation = data.presentations.find(
    (item) => getId(item) === presentationId
  );

  const selectedConsultant = availableConsultants.find(
    (item) => getId(item) === consultantId
  );

  const filteredAssignments = useMemo(() => {
    const term = search.trim().toLowerCase();

    if (!term) return data.assignments;

    return data.assignments.filter((item) => {
      const values = [
        item.presentationTitle,
        item.presentation_title,
        item.title,
        item.presenterName,
        item.presenter_name,
        item.consultantName,
        item.consultant_name,
        item.status,
        item.reviewStatus,
        item.review_status,
      ];

      return values.some((value) =>
        String(value || '').toLowerCase().includes(term)
      );
    });
  }, [data.assignments, search]);

  async function handleAssign(event) {
    event.preventDefault();

    setError('');
    setNotice('');

    if (!presentationId || !consultantId) {
      setError('Select both a presentation and a consultant.');
      return;
    }

    const pair = `${presentationId}:${consultantId}`;

    if (assignedPairs.has(pair)) {
      setError('This presentation is already assigned to that consultant.');
      return;
    }

    setSaving(true);

    try {
      await bootstrap();
      await api.assignPresentation(presentationId, consultantId);

      setNotice('Presentation assigned successfully.');
      setPresentationId('');
      setConsultantId('');

      await loadAssignments(true);

      if (onChanged) await onChanged(true);
    } catch (e) {
      setError(e.message || 'Could not assign this presentation.');
    } finally {
      setSaving(false);
    }
  }

  async function handleUnassign(assignment) {
    const id = getId(assignment);
    const assignedPresentationId = getPresentationId(assignment);
    const assignedConsultantId = getConsultantId(assignment);

    if (!assignedPresentationId || !assignedConsultantId) {
      setError('The assignment is missing its presentation or consultant ID.');
      setConfirmRemoval(null);
      return;
    }

    setRemovingId(id || `${assignedPresentationId}:${assignedConsultantId}`);
    setError('');
    setNotice('');
    setConfirmRemoval(null);

    try {
      await bootstrap();

      await api.unassignPresentation(
        assignedPresentationId,
        assignedConsultantId
      );

      setNotice('Assignment removed successfully.');

      await loadAssignments(true);

      if (onChanged) await onChanged(true);
    } catch (e) {
      setError(e.message || 'Could not remove the assignment.');
    } finally {
      setRemovingId('');
    }
  }

  return (
    <section className="assignments-page">
      <div className="workspace-heading-row">
        <div>
          <div className="eyebrow">
            <ClipboardList size={14} /> ADMINISTRATION
          </div>
          <h1>Manage assignments</h1>
          <p>
            Connect submitted presentations with consultants who will review
            them.
          </p>
        </div>

        <button
          type="button"
          className="outline-btn"
          onClick={() => loadAssignments(true)}
          disabled={loading || refreshing}
        >
          <RefreshCw
            size={16}
            className={refreshing ? 'spin' : ''}
          />
          Refresh
        </button>
      </div>

      {error && (
        <div className="notice error assignment-notice" role="alert">
          <AlertCircle size={17} />
          <span>{error}</span>
          <button
            type="button"
            onClick={() => setError('')}
            aria-label="Dismiss error"
          >
            ×
          </button>
        </div>
      )}

      {notice && (
        <div className="notice success assignment-notice" role="status">
          <CheckCircle2 size={17} />
          <span>{notice}</span>
          <button
            type="button"
            onClick={() => setNotice('')}
            aria-label="Dismiss notice"
          >
            ×
          </button>
        </div>
      )}

      <div className="assignment-stat-grid">
        <article className="assignment-stat-card">
          <span className="assignment-stat-icon blue">
            <FileText size={19} />
          </span>
          <div>
            <span>Presentations</span>
            <strong>{data.presentations.length}</strong>
          </div>
        </article>

        <article className="assignment-stat-card">
          <span className="assignment-stat-icon green">
            <Users size={19} />
          </span>
          <div>
            <span>Active consultants</span>
            <strong>{availableConsultants.length}</strong>
          </div>
        </article>

        <article className="assignment-stat-card">
          <span className="assignment-stat-icon violet">
            <ClipboardList size={19} />
          </span>
          <div>
            <span>Assignments</span>
            <strong>{data.assignments.length}</strong>
          </div>
        </article>
      </div>

      <section className="panel assignment-form-panel">
        <div className="panel-heading">
          <div>
            <h3>Assign a presentation</h3>
            <p>Choose a submission and an active consultant.</p>
          </div>
          <span className="panel-icon">
            <UserPlus size={17} />
          </span>
        </div>

        <form className="assignment-form" onSubmit={handleAssign}>
          <label>
            Presentation
            <select
              value={presentationId}
              onChange={(event) => setPresentationId(event.target.value)}
              required
              disabled={loading || saving || data.presentations.length === 0}
            >
              <option value="">Choose a presentation</option>
              {data.presentations.map((item) => (
                <option key={getId(item)} value={getId(item)}>
                  {item.title || item.presentationTitle || `Presentation #${getId(item)}`}
                  {item.presenterName || item.presenter_name
                    ? ` — ${item.presenterName || item.presenter_name}`
                    : ''}
                </option>
              ))}
            </select>
          </label>

          <label>
            Consultant
            <select
              value={consultantId}
              onChange={(event) => setConsultantId(event.target.value)}
              required
              disabled={loading || saving || availableConsultants.length === 0}
            >
              <option value="">Choose a consultant</option>
              {availableConsultants.map((item) => (
                <option key={getId(item)} value={getId(item)}>
                  {item.fullName || item.full_name || item.name || 'Consultant'}
                  {item.email ? ` (${item.email})` : ''}
                </option>
              ))}
            </select>
          </label>

          <button
            className="primary-action-btn"
            type="submit"
            disabled={
              loading ||
              saving ||
              !presentationId ||
              !consultantId ||
              assignedPairs.has(`${presentationId}:${consultantId}`)
            }
          >
            <UserPlus size={16} />
            {saving ? 'Assigning…' : 'Assign consultant'}
          </button>
        </form>

        {selectedPresentation && selectedConsultant && (
          <div className="assignment-selection-preview">
            <CheckCircle2 size={16} />
            <span>
              <b>{selectedPresentation.title}</b> will be assigned to{' '}
              <b>
                {selectedConsultant.fullName ||
                  selectedConsultant.full_name ||
                  selectedConsultant.name}
              </b>.
            </span>
          </div>
        )}

        {!loading && data.presentations.length === 0 && (
          <p className="assignment-helper">
            No presentations are available to assign yet. Once presenters
            submit their work, it will appear here.
          </p>
        )}

        {!loading && availableConsultants.length === 0 && (
          <p className="assignment-helper">
            No active consultants were returned by the server. Create or
            activate a consultant account before assigning reviews.
          </p>
        )}
      </section>

      <section className="panel assignment-list-panel">
        <div className="panel-heading assignment-list-heading">
          <div>
            <h3>Current assignments</h3>
            <p>Track who is reviewing each presentation.</p>
          </div>

          <label className="assignment-search">
            <Search size={16} />
            <input
              type="search"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search assignments"
              aria-label="Search assignments"
            />
          </label>
        </div>

        {loading ? (
          <div className="loading-panel">
            <span className="loader" />
            <p>Loading assignments…</p>
          </div>
        ) : filteredAssignments.length === 0 ? (
          <div className="empty-state assignment-empty">
            <span className="empty-state-icon">
              <ClipboardList size={25} />
            </span>
            <h3>
              {search ? 'No matching assignments' : 'No assignments yet'}
            </h3>
            <p>
              {search
                ? 'Try a different search term.'
                : 'Use the form above to assign a presentation to a consultant.'}
            </p>
          </div>
        ) : (
          <div className="assignment-table-wrap">
            <table className="assignment-table">
              <thead>
                <tr>
                  <th>Presentation</th>
                  <th>Presenter</th>
                  <th>Consultant</th>
                  <th>Assignment</th>
                  <th>Review</th>
                  <th>Assigned</th>
                  <th>Action</th>
                </tr>
              </thead>

              <tbody>
                {filteredAssignments.map((item, index) => {
                  const presentationTitle =
                    item.presentationTitle ||
                    item.presentation_title ||
                    item.title ||
                    'Untitled presentation';

                  const presenterName =
                    item.presenterName || item.presenter_name || '—';

                  const consultantName =
                    item.consultantName || item.consultant_name || '—';

                  const assignmentStatus =
                    item.status || item.assignmentStatus || 'assigned';

                  const reviewStatus =
                    item.reviewStatus ||
                    item.review_status ||
                    'not_started';

                  const rowId =
                    getId(item) ||
                    `${getPresentationId(item)}:${getConsultantId(item)}`;

                  const isRemoving = removingId === rowId;

                  return (
                    <tr key={rowId || index}>
                      <td>
                        <div className="assignment-title-cell">
                          <span className="file-tile">
                            <FileText size={17} />
                          </span>
                          <b>{presentationTitle}</b>
                        </div>
                      </td>

                      <td>{presenterName}</td>

                      <td>
                        <div className="assignment-consultant-cell">
                          <span className="assignment-person-icon">
                            <UserRound size={15} />
                          </span>
                          <span>{consultantName}</span>
                        </div>
                      </td>

                      <td>
                        <span className={`assignment-status status-${assignmentStatus}`}>
                          {statusLabel(assignmentStatus)}
                        </span>
                      </td>

                      <td>
                        <span className={`assignment-status review-${reviewStatus}`}>
                          {statusLabel(reviewStatus)}
                        </span>
                      </td>

                      <td>{dateLabel(item.assignedAt || item.assigned_at)}</td>

                      <td>
                        {confirmRemoval === rowId ? (
                          <div className="assignment-confirm-actions">
                            <button
                              type="button"
                              className="assignment-confirm-remove"
                              disabled={isRemoving}
                              onClick={() => handleUnassign(item)}
                            >
                              {isRemoving ? 'Removing…' : 'Confirm'}
                            </button>
                            <button
                              type="button"
                              className="assignment-cancel-remove"
                              disabled={isRemoving}
                              onClick={() => setConfirmRemoval(null)}
                            >
                              Cancel
                            </button>
                          </div>
                        ) : (
                          <button
                            type="button"
                            className="assignment-remove-btn"
                            disabled={Boolean(removingId)}
                            onClick={() => setConfirmRemoval(rowId)}
                            title="Remove assignment"
                          >
                            <UserMinus size={15} />
                            Remove
                          </button>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </section>

      <p className="assignment-security-note">
        <Clock3 size={15} />
        Removing an assignment revokes that consultant’s access to the
        presentation through the assignment-protected endpoints.
      </p>
    </section>
  );
}
