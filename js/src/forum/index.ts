import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import UserPage from 'flarum/forum/components/UserPage';
import LinkButton from 'flarum/common/components/LinkButton';
import Badge from 'flarum/common/components/Badge';
import User from 'flarum/common/models/User';
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

  // Featured-project badge next to the username everywhere user badges render
  // (posts, user card, …). Reads the denormalised `projectFeatured` snapshot on
  // the user model, so there's no per-render query.
  extend(User.prototype, 'badges', function (this: any, items: any) {
    const featured = this.attribute('projectFeatured');
    if (!featured || !featured.title) return;

    items.add(
      'projectFeatured',
      Badge.component({
        icon: featured.icon || 'fas fa-cube',
        type: 'projects-featured',
        label: featured.title,
        style: featured.color ? { backgroundColor: featured.color } : undefined,
      }),
      15
    );
  });
});
