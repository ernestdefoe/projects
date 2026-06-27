import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
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

  like(project: Project) {
    if (!app.session.user) {
      app.modal.show(() => import('flarum/forum/components/LogInModal'));
      return;
    }
    likeProject(project.id)
      .then((res) => {
        const updated = res.data;
        const i = this.projects.findIndex((p) => p.id === updated.id);
        if (i >= 0) this.projects[i] = updated;
        m.redraw();
      })
      .catch(() => app.alerts.show({ type: 'error' }, t('like_error')));
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

        cfg.categories.length
          ? m('select.FormControl.ProjectsPage-filter', {
              value: this.category,
              onchange: (e: any) => { this.category = e.target.value; this.load(); },
            }, [
              m('option', { value: '' }, t('all_categories')),
              ...cfg.categories.map((c) => m('option', { value: c.slug }, c.name)),
            ])
          : null,

        m('select.FormControl.ProjectsPage-filter', {
          value: this.sort,
          onchange: (e: any) => { this.sort = e.target.value; this.load(); },
        }, [
          m('option', { value: 'recent' }, t('sort.recent')),
          m('option', { value: 'popular' }, t('sort.popular')),
          m('option', { value: 'title' }, t('sort.title')),
        ]),

        canModerate
          ? m('select.FormControl.ProjectsPage-filter', {
              value: this.status,
              onchange: (e: any) => { this.status = e.target.value; this.load(); },
            }, [
              m('option', { value: '' }, t('status.all')),
              m('option', { value: 'pending' }, t('status.pending')),
              m('option', { value: 'published' }, t('status.published')),
              m('option', { value: 'rejected' }, t('status.rejected')),
            ])
          : null,
      ]),

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
