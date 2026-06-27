import app from 'flarum/forum/app';
import UserPage from 'flarum/forum/components/UserPage';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import ProjectCard from './ProjectCard';
import { listProjects, likeProject, featureProject, type Project } from '../../common/api';

declare const m: any;
const t = (k: string, p?: any): any => app.translator.trans('ernestdefoe-projects.forum.' + k, p);

/** "Projects" tab on a member's profile — their published projects as cards. */
export default class UserProjectsPage extends UserPage {
  projects: Project[] = [];
  loadingProjects = true;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.loadUser(m.route.param('username'));
  }

  show(user: any) {
    super.show(user);
    this.loadProjects(user.id());
  }

  loadProjects(userId: number) {
    this.loadingProjects = true;
    listProjects({ user: userId, perPage: 50 })
      .then((res) => {
        this.projects = res.data;
        this.loadingProjects = false;
        m.redraw();
      })
      .catch(() => {
        this.loadingProjects = false;
        m.redraw();
      });
  }

  like(project: Project) {
    if (!app.session.user) return;
    likeProject(project.id).then((res) => {
      const i = this.projects.findIndex((p) => p.id === res.data.id);
      if (i >= 0) this.projects[i] = res.data;
      m.redraw();
    });
  }

  feature(project: Project) {
    featureProject(project.id)
      .then((res) => {
        // The server clears any previously-featured project; reflect that locally.
        this.projects = this.projects.map((p) =>
          p.id === res.data.id ? res.data : { ...p, isFeatured: false }
        );
        m.redraw();
      })
      .catch(() => app.alerts.show({ type: 'error' }, t('like_error')));
  }

  content() {
    if (this.loadingProjects) {
      return m('.UserProjectsPage', m(LoadingIndicator, { size: 'large' }));
    }

    if (!this.projects.length) {
      return m('.UserProjectsPage.ProjectsPage-empty', [m('i.fas.fa-cubes'), m('p', t('profile.empty'))]);
    }

    return m(
      '.UserProjectsPage',
      m('.ProjectsGrid', this.projects.map((p) => m(ProjectCard, { key: p.id, project: p, onLike: (x: Project) => this.like(x), onFeature: (x: Project) => this.feature(x) })))
    );
  }
}
