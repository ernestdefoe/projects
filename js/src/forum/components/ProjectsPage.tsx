import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Select from 'flarum/common/components/Select';
import extractText from 'flarum/common/utils/extractText';
import ProjectCard from './ProjectCard';
import ProjectFormModal from './ProjectFormModal';
import { config, listProjects, likeProject, type ListParams, type Project } from '../../common/api';

declare const m: any;
const t = (k: string, p?: any): any => app.translator.trans('ernestdefoe-projects.forum.' + k, p);

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

  oninit(vnode: any) {
    super.oninit(vnode);
    app.setTitle(t('page_title') as unknown as string);
    this.category = (m.route.param('category') as string) || '';
    this.load();
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

        // Core's Select component instead of raw native selects: themes style
        // .Select consistently, while bare FormControl selects inherited
        // whatever height/line-height the theme set and clipped their labels
        // (the "filter labels aren't displaying properly" report). The
        // category filter is a pill row below instead of a dropdown.
        m(Select, {
          wrapperAttrs: { className: 'ProjectsPage-filter' },
          value: this.sort,
          options: {
            recent: extractText(t('sort.recent')),
            popular: extractText(t('sort.popular')),
            title: extractText(t('sort.title')),
          },
          onchange: (v: string) => { this.sort = v; this.load(); },
        }),

        canModerate
          ? m(Select, {
              wrapperAttrs: { className: 'ProjectsPage-filter' },
              value: this.status,
              options: {
                '': extractText(t('status.all')),
                pending: extractText(t('status.pending')),
                published: extractText(t('status.published')),
                rejected: extractText(t('status.rejected')),
              },
              onchange: (v: string) => { this.status = v; this.load(); },
            })
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
