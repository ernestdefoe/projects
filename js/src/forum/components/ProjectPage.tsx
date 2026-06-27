import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Link from 'flarum/common/components/Link';
import ProjectFormModal from './ProjectFormModal';
import { getProject, likeProject, featureProject, deleteProject, moderateProject, type Project } from '../../common/api';
import { authorAvatar } from '../authorAvatar';

declare const m: any;
const t = (k: string, p?: any): any => app.translator.trans('ernestdefoe-projects.forum.' + k, p);

/** Full project detail page (route projects.show, /projects/p/:slug). */
export default class ProjectPage extends Page {
  project: Project | null = null;
  loading = true;
  notFound = false;
  error: any = null;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.loadProject(m.route.param('slug'));
  }

  onupdate() {
    const slug = m.route.param('slug');
    if (this.project && this.project.slug !== slug && !this.loading) {
      this.loadProject(slug);
    }
  }

  loadProject(slug: string) {
    this.loading = true;
    this.notFound = false;
    this.error = null;
    getProject(slug)
      .then((res) => {
        this.project = res.data;
        app.setTitle(res.data.title);
        this.loading = false;
        m.redraw();
      })
      .catch((e) => {
        // Distinguish a genuine 404 from access/server/network failures so the
        // user knows whether to retry or whether they simply can't see it.
        const status = e?.status ?? e?.response?.status;
        if (status === 404) {
          this.notFound = true;
        } else {
          this.error = status === 401 || status === 403 ? t('load_error_forbidden') : t('project_error');
        }
        this.loading = false;
        m.redraw();
      });
  }

  view() {
    if (this.loading) return m('.ProjectPage', m('.container', m(LoadingIndicator, { size: 'large' })));
    if (this.error) {
      return m('.ProjectPage', m('.container', m('.ProjectPage-empty.ProjectsPage-error', [
        m('i.fas.fa-circle-exclamation'),
        m('p', this.error),
        Button.component({ className: 'Button', onclick: () => this.loadProject(m.route.param('slug')) }, t('retry')),
      ])));
    }
    if (this.notFound || !this.project) return m('.ProjectPage', m('.container', m('.ProjectPage-empty', t('not_found'))));

    const p = this.project;
    const accent = p.primaryCategory?.color || 'var(--primary-color)';

    return m('.ProjectPage', { style: { '--project-accent': accent } }, [
      p.image ? m('.ProjectPage-hero', m('img', { src: p.image, alt: p.title })) : null,

      m('.container.ProjectPage-container', [
        m(Link, { href: app.route('projects'), className: 'ProjectPage-back' }, [m('i.fas.fa-arrow-left'), ' ', t('back')]),

        p.status !== 'published'
          ? m('.ProjectPage-statusBar.ProjectPage-statusBar--' + p.status, [
              t('status.' + p.status),
              p.status === 'rejected' && p.rejectionReason ? m('span', ' — ' + p.rejectionReason) : null,
            ])
          : null,

        p.categories.length
          ? m('.ProjectPage-cats', p.categories.map((c) =>
              m(Link, { href: app.route('projects') + '?category=' + c.slug, className: 'ProjectCard-cat', style: c.color ? { '--project-accent': c.color } : undefined }, [
                c.icon ? m('i', { className: c.icon }) : null, ' ', c.name,
              ])
            ))
          : null,

        m('h1.ProjectPage-title', p.title),

        p.author
          ? m(Link, { href: app.route('user', { username: p.author.username }), className: 'ProjectPage-author' }, [
              authorAvatar(p.author),
              m('span', p.author.displayName),
            ])
          : null,

        p.coAuthors && p.coAuthors.length ? this.coAuthorsLine(p) : null,

        this.fields(p),

        p.contentHtml ? m('.ProjectPage-content.Post-body', m.trust(p.contentHtml)) : null,

        m('.ProjectPage-actions', [
          ...p.links.map((l) =>
            m('a.Button' + (l.isPrimary ? '.Button--primary' : ''), { href: l.url, target: '_blank', rel: 'noopener noreferrer nofollow' }, [
              l.icon ? m('i.Button-icon', { className: l.icon }) : null, m('span.Button-label', l.label),
            ])
          ),
          p.discussionId
            ? m('a.Button', { href: app.forum.attribute('baseUrl') + '/d/' + p.discussionId }, [m('i.Button-icon.fas.fa-comments'), m('span.Button-label', t('discuss'))])
            : null,
          m('button.Button.ProjectPage-like' + (p.liked ? '.is-liked' : ''), { type: 'button', disabled: p.liked === null, onclick: () => this.like() }, [
            m('i.Button-icon', { className: (p.liked ? 'fas' : 'far') + ' fa-heart' }), m('span.Button-label', String(p.likesCount)),
          ]),
        ]),

        this.management(p),
      ]),
    ]);
  }

  coAuthorsLine(p: Project) {
    return m(
      '.ProjectPage-coAuthors',
      [
        t('with'),
        ' ',
        ...p.coAuthors.map((a, i) => [
          i > 0 ? ', ' : null,
          a.username
            ? m(Link, { href: app.route('user', { username: a.username }) }, a.displayName || a.username)
            : m('span', a.name),
        ]),
      ]
    );
  }

  fields(p: Project) {
    if (!p.fields.length) return null;
    return m('.ProjectPage-fields', p.fields.map((f) =>
      m('.ProjectPage-fieldRow', [
        m('.ProjectPage-fieldName', [f.icon ? m('i', { className: f.icon }) : null, ' ', f.name]),
        m('.ProjectPage-fieldVal', (f.prefix || '') + (f.type === 'boolean' ? t('yes') : f.value) + (f.suffix ? ' ' + f.suffix : '')),
      ])
    ));
  }

  management(p: Project) {
    const items: any[] = [];

    if (p.canModerate && p.status === 'pending') {
      items.push(Button.component({ className: 'Button Button--primary', icon: 'fas fa-check', onclick: () => this.moderate('approve') }, t('moderate.approve')));
      items.push(Button.component({ className: 'Button', icon: 'fas fa-xmark', onclick: () => this.moderate('reject') }, t('moderate.reject')));
    }
    if (p.canFeature) {
      items.push(Button.component({
        className: 'Button' + (p.isFeatured ? ' Button--primary' : ''),
        icon: p.isFeatured ? 'fas fa-star' : 'far fa-star',
        onclick: () => this.feature(),
      }, p.isFeatured ? t('featured') : t('feature')));
    }
    if (p.canEdit) {
      items.push(Button.component({ className: 'Button', icon: 'fas fa-pencil', onclick: () => app.modal.show(ProjectFormModal, { project: p, onsave: () => this.loadProject(p.slug) }) }, t('edit')));
    }
    if (p.canDelete) {
      items.push(Button.component({ className: 'Button Button--danger', icon: 'fas fa-trash', onclick: () => this.remove() }, t('delete')));
    }

    return items.length ? m('.ProjectPage-manage', items) : null;
  }

  like() {
    if (!app.session.user || !this.project) return;
    likeProject(this.project.id)
      .then((res) => { this.project = res.data; m.redraw(); })
      .catch(() => app.alerts.show({ type: 'error' }, t('like_error')));
  }

  feature() {
    if (!this.project) return;
    featureProject(this.project.id)
      .then((res) => { this.project = res.data; m.redraw(); })
      .catch(() => app.alerts.show({ type: 'error' }, t('like_error')));
  }

  moderate(action: 'approve' | 'reject') {
    if (!this.project) return;
    let reason: string | undefined;
    if (action === 'reject') {
      reason = (window.prompt(t('moderate.reason_prompt') as unknown as string) as string) || '';
    }
    moderateProject(this.project.id, action, reason).then((res) => { this.project = res.data; m.redraw(); });
  }

  remove() {
    if (!this.project || !confirm(t('confirm_delete') as unknown as string)) return;
    deleteProject(this.project.id).then(() => m.route.set(app.route('projects')));
  }
}
