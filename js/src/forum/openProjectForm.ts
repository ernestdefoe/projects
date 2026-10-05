import app from 'flarum/forum/app';
import { loadConfig } from '../common/api';

/**
 * Opens the create/edit form. The form is its own chunk, fetched with the
 * admin-defined definitions it renders from, only when someone opens it.
 */
export default function openProjectForm(attrs: Record<string, unknown>) {
  return app.modal.show(
    () => Promise.all([import('./components/ProjectFormModal'), loadConfig()]).then(([mod]) => mod),
    attrs
  );
}
