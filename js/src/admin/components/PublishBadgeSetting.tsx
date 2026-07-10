import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Select from 'flarum/common/components/Select';
import { getConfig } from '../../common/api';

declare const m: any;
const t = (k: string): any => app.translator.trans('ernestdefoe-projects.admin.' + k);

const KEY = 'ernestdefoe-projects.publish_badge_id';

/**
 * The "award a FoF badge on publish" picker.
 *
 * A live component rather than a declarative `.setting({type:'select'})`,
 * because that captures its `options` when the settings page is first built and
 * never rebuilds them after the async badge fetch resolves — which left the
 * dropdown stuck on just "None" even when badges existed. This fetches the badge
 * list itself, redraws when it arrives, and saves the choice immediately.
 */
export default class PublishBadgeSetting extends Component {
  badges: { id: number; name: string }[] = [];
  loading = true;
  saving = false;

  oninit(vnode: any) {
    super.oninit(vnode);
    getConfig()
      .then((r) => { this.badges = r.data.badges || []; this.loading = false; m.redraw(); })
      .catch(() => { this.loading = false; m.redraw(); });
  }

  view() {
    const options: Record<string, string> = { '': t('settings.publish_badge_none') };
    for (const b of this.badges) options[String(b.id)] = b.name;
    const value = String(app.data.settings[KEY] ?? '');

    return m('.Form-group.ProjectsPublishBadge', [
      m('label', t('settings.publish_badge_id')),
      m(Select, { value, options, disabled: this.loading || this.saving, onchange: (v: string) => this.save(v) }),
      m('.helpText', t('settings.publish_badge_id_help')),
    ]);
  }

  save(value: string) {
    app.data.settings[KEY] = value;
    this.saving = true;
    m.redraw();
    app
      .request({ method: 'POST', url: app.forum.attribute('apiUrl') + '/settings', body: { [KEY]: value } })
      .then(() => { this.saving = false; m.redraw(); })
      .catch(() => { this.saving = false; m.redraw(); });
  }
}
