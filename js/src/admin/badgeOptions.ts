import app from 'flarum/admin/app';
import { getConfig } from '../common/api';

declare const m: any;

// Lazily fetched once (memoized) the first time the settings page renders the
// badge picker, so the "award on publish" setting is a real dropdown of badge
// names instead of a raw numeric ID.
let badges: { id: number; name: string }[] | null = null;
let loading = false;

/** {value:label} options for the publish-badge <select>: "" = award nothing. */
export function publishBadgeOptions(): Record<string, string> {
  if (badges === null && !loading) {
    loading = true;
    getConfig()
      .then((r) => {
        badges = r.data.badges || [];
        m.redraw();
      })
      .catch(() => {
        badges = [];
      });
  }

  const none = app.translator.trans('ernestdefoe-projects.admin.settings.publish_badge_none') as unknown as string;
  const opts: Record<string, string> = { '': none };
  for (const b of badges || []) {
    opts[String(b.id)] = b.name;
  }

  return opts;
}
