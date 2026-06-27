import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import UserPage from 'flarum/forum/components/UserPage';
import PostUser from 'flarum/forum/components/PostUser';
import LinkButton from 'flarum/common/components/LinkButton';
import Link from 'flarum/common/components/Link';
import ProjectsPage from './components/ProjectsPage';
import ProjectPage from './components/ProjectPage';
import UserProjectsPage from './components/UserProjectsPage';

declare const m: any;
const t = (k: string, p?: any): any => app.translator.trans('ernestdefoe-projects.forum.' + k, p);

app.initializers.add('ernestdefoe/projects', () => {
  app.routes['projects'] = { path: '/projects', component: ProjectsPage };
  app.routes['projects.show'] = { path: '/projects/p/:slug', component: ProjectPage };
  app.routes['user.projects'] = { path: '/u/:username/projects', component: UserProjectsPage };

  // Sidebar nav link (sits with "All Discussions").
  extend(IndexSidebar.prototype, 'navItems', function (items: any) {
    items.add(
      'projects',
      LinkButton.component({ href: app.route('projects'), icon: 'fas fa-cubes' }, t('nav')),
      5
    );
  });

  // "Projects" tab on member profiles.
  extend(UserPage.prototype, 'navItems', function (this: any, items: any) {
    const user = this.user;
    if (!user) return;
    items.add(
      'projects',
      LinkButton.component(
        { href: app.route('user.projects', { username: user.username() }), icon: 'fas fa-cubes' },
        t('profile.tab')
      ),
      80
    );
  });

  // Featured-project badge next to the username, beside FoF Badges' primary
  // badge (.PrimaryBadge) rather than among the group badges. Reads the
  // denormalised `projectFeatured` snapshot (no per-render query) and links to
  // the project. Mirrors how fof/badges injects its PrimaryBadge into PostUser.
  extend(PostUser.prototype, 'view', function (this: any, vnode: any) {
    const post = this.attrs.post;
    const user = post && typeof post.user === 'function' ? post.user() : null;
    const featured = user && typeof user.attribute === 'function' ? user.attribute('projectFeatured') : null;
    if (!featured || !featured.title || !featured.slug) return;
    if (!vnode || !Array.isArray(vnode.children)) return;

    vnode.children.push(
      m(
        Link,
        {
          href: app.route('projects.show', { slug: featured.slug }),
          className: 'PrimaryBadge ProjectFeaturedBadge',
          title: featured.title,
          style: featured.color ? { '--project-accent': featured.color } : undefined,
          onclick: (e: Event) => e.stopPropagation(),
        },
        [m('i', { className: featured.icon || 'fas fa-cube' }), m('span.PrimaryBadge-name', featured.title)]
      )
    );
  });
});
