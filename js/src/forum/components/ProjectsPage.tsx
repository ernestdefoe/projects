import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import extractText from 'flarum/common/utils/extractText';
import ProjectCard from './ProjectCard';
import StyledSelect from './StyledSelect';
import ProjectPage from './ProjectPage';
import ProjectFormModal from './ProjectFormModal';
import { config, listProjects, likeProject, type ListParams, type Project } from '../../common/api';

declare const m: any;
const t = (k: string, p?: any): any => app.translator.trans('ernestdefoe-projects.forum.' + k, p);

// Remembers the browse state (loaded cards, filters, scroll position) so that
// opening a project and clicking Back returns you exactly where you were,
// instead of reloading the grid from the top. Restored only on a genuine
// back-navigation from a project page (see oninit).
let browseCache: {
  category: string; q: string; sort: 'recent' | 'popular' | 'title'; status: string;
  projects: Project[]; page: number; hasMore: boolean; total: number; scrollY: number;
} | null = null;

/**
 * The browse page: a search bar, category filter, sort control and an
 * "Add project" button across the top, then a responsive grid of cards with
 * load-more pagination.
 */
export default class ProjectsPage extends Page {
  projects: Project[] = [];
  loading = true;
  loadingMore = false;
  hasMore = false;
  page = 1;
  total = 0;
  error: any = null;

  q = '';
  category = '';
  sort: 'recent' | 'popular' | 'title' = 'recent';
  status = '';
  private debounce: any = null;
  private restoreScrollTo: number | null = null;

  oninit(vnode: any) {
    super.oninit(vnode);
    app.setTitle(t('page_title') as unknown as string);
    this.category = (m.route.param('category') as string) || '';

    // Restore the remembered grid only when returning from a project page for
    // the same filter — a fresh visit (or a different tag) loads normally.
    const cameFromProject = !!(app.previous && app.previous.matches && app.previous.matches(ProjectPage));
    if (browseCache && cameFromProject && browseCache.category === this.category) {
      this.q = browseCache.q;
      this.sort = browseCache.sort;
      this.status = browseCache.status;
      this.projects = browseCache.projects;
      this.page = browseCache.page;
      this.hasMore = browseCache.hasMore;
      this.total = browseCache.total;
      this.loading = false;
      this.restoreScrollTo = browseCache.scrollY;
    } else {
      browseCache = null;
      this.load();
    }
  }

  oncreate(vnode: any) {
    super.oncreate(vnode);
    this.tryRestoreScroll();
  }

  onupdate(vnode: any) {
    super.onupdate(vnode);
    this.tryRestoreScroll();
  }

  /** Once the restored cards are in the DOM, jump back to the saved offset. */
  tryRestoreScroll() {
    if (this.restoreScrollTo == null || this.loading || !this.projects.length) return;
    const y = this.restoreScrollTo;
    this.restoreScrollTo = null;
    requestAnimationFrame(() => window.scrollTo(0, y));
  }

  onremove() {
    // Snapshot the current browse state so a later Back can restore it.
    browseCache = {
      category: this.category, q: this.q, sort: this.sort, status: this.status,
      projects: this.projects, page: this.page, hasMore: this.hasMore, total: this.total,
      scrollY: window.pageYOffset,
    };
  }

  params(): ListParams {
    return {
      q: this.q || undefined,
      category: this.category || undefined,
      sort: this.sort,
      status: this.status || undefined,
      page: this.page,
    };
  }

  /** Turn an app.request rejection into a human, status-aware message. */
  errorText(e: any): any {
    const status = e?.status ?? e?.response?.status;
    if (status === 401 || status === 403) return t('load_error_forbidden');
    return t('load_error');
  }

  load() {
    this.loading = true;
    this.error = null;
    this.page = 1;
    listProjects(this.params())
      .then((res) => {
        this.projects = res.data;
        this.total = res.meta.total;
        this.hasMore = res.meta.hasMore;
        this.loading = false;
        m.redraw();
      })
      .catch((e) => {
        this.error = this.errorText(e);
        this.loading = false;
        m.redraw();
      });
  }

  loadMore() {
    if (this.loadingMore || !this.hasMore) return;
    this.loadingMore = true;
    this.page++;
    listProjects(this.params())
      .then((res) => {
        this.projects = this.projects.concat(res.data);
        this.hasMore = res.meta.hasMore;
        this.loadingMore = false;
        m.redraw();
      })
      .catch((e) => {
        // Keep the projects already shown; surface the failure as an alert.
        this.page--;
        this.loadingMore = false;
        app.alerts.show({ type: 'error' }, this.errorText(e));
        m.redraw();
      });
  }

  search(value: string) {
    this.q = value;
    clearTimeout(this.debounce);
    this.debounce = setTimeout(() => this.load(), 350);
  }

  /** One category pill; null = the "all categories" pill. */
  catPill(c: { slug: string; name: string; icon?: string | null; color?: string | null; description?: string | null } | null) {
    const slug = c ? c.slug : '';
    const on = this.category === slug;
    return m('button.ProjectsPage-catPill' + (on ? '.is-on' : ''), {
      type: 'button',
      style: c?.color ? { '--project-accent': c.color } : undefined,
      title: c?.description || undefined,
      onclick: () => {
        this.category = slug;
        this.load();
      },
    }, [c?.icon ? m('i', { className: c.icon }) : null, c?.icon ? ' ' : null, c ? c.name : t('all_categories')]);
  }

  like(project: Project) {
    if (!app.session.user) {
      app.modal.show(() => import('flarum/forum/components/LogInModal'));
      return;
    }
    // Optimistic: flip immediately (waiting on the round-trip made the button
    // feel ~a second late), reconcile with the server's copy, roll back on error.
    const wasLiked = !!project.liked;
    project.liked = !wasLiked;
    project.likesCount += wasLiked ? -1 : 1;
    likeProject(project.id)
      .then((res) => {
        const updated = res.data;
        const i = this.projects.findIndex((p) => p.id === updated.id);
        if (i >= 0) this.projects[i] = updated;
        m.redraw();
      })
      .catch(() => {
        project.liked = wasLiked;
        project.likesCount += wasLiked ? 1 : -1;
        m.redraw();
        app.alerts.show({ type: 'error' }, t('like_error'));
      });
  }

  add() {
    app.modal.show(ProjectFormModal, { onsave: () => this.load() });
  }

  view() {
    const cfg = config();
    const canCreate = !!app.forum.attribute('canCreateProject');
    const canModerate = !!app.forum.attribute('canModerateProjects');

    return m('.ProjectsPage', m('.container', [
      m('.ProjectsPage-header', [
        m('h1.ProjectsPage-title', t('page_title')),
        canCreate
          ? Button.component({ className: 'Button Button--primary', icon: 'fas fa-plus', onclick: () => this.add() }, t('add_project'))
          : null,
      ]),

      m('.ProjectsPage-tools', [
        m('.ProjectsPage-search', [
          m('i.fas.fa-magnifying-glass.ProjectsPage-searchIcon'),
          m('input.FormControl', {
            type: 'search',
            placeholder: t('search_placeholder'),
            value: this.q,
            oninput: (e: any) => this.search(e.target.value),
          }),
        ]),

        // StyledSelect renders a real Flarum Dropdown (ul.Dropdown-menu.dropdown-menu)
        // rather than a native <select> whose option popup can't be themed — the
        // same styled menu the discussion list uses for its latest/top/newest
        // filter, as requested in the thread. The category filter is the pill row
        // below instead of a dropdown.
        m('.ProjectsPage-filter', m(StyledSelect, {
          value: this.sort,
          options: {
            recent: extractText(t('sort.recent')),
            popular: extractText(t('sort.popular')),
            title: extractText(t('sort.title')),
          },
          onchange: (v: string) => { this.sort = v as typeof this.sort; this.load(); },
        })),

        canModerate
          ? m('.ProjectsPage-filter', m(StyledSelect, {
              value: this.status,
              options: {
                '': extractText(t('status.all')),
                pending: extractText(t('status.pending')),
                published: extractText(t('status.published')),
                rejected: extractText(t('status.rejected')),
              },
              onchange: (v: string) => { this.status = v; this.load(); },
            }))
          : null,
      ]),

      // Category filter as pill buttons — more visual than the old dropdown,
      // and each pill carries its description as a hover tooltip.
      cfg.categories.length
        ? m('.ProjectsPage-catPills', [
            this.catPill(null),
            ...cfg.categories.map((c) => this.catPill(c)),
          ])
        : null,

      this.loading
        ? m('.ProjectsPage-loading', m(LoadingIndicator, { size: 'large' }))
        : this.error
          ? m('.ProjectsPage-empty.ProjectsPage-error', [
              m('i.fas.fa-circle-exclamation'),
              m('p', this.error),
              Button.component({ className: 'Button', onclick: () => this.load() }, t('retry')),
            ])
          : this.projects.length
            ? [
                m('.ProjectsGrid', this.projects.map((p) => m(ProjectCard, { key: p.id, project: p, onLike: (x: Project) => this.like(x) }))),
                this.hasMore
                  ? m('.ProjectsPage-more', Button.component({ className: 'Button', loading: this.loadingMore, onclick: () => this.loadMore() }, t('load_more')))
                  : null,
              ]
            : m('.ProjectsPage-empty', [m('i.fas.fa-cubes'), m('p', t('empty'))]),
    ]));
  }
}
