import Component from 'flarum/common/Component';

declare const m: any;

/**
 * The project grid's shape while it loads.
 *
 * 🚨 Every number is MEASURED against the rendered grid, not chosen: a card is
 * 537px tall in a 280px-minimum auto-fill grid with an 18px gap, and its body
 * carries 14/16/10 of padding under the media. A skeleton of the wrong size
 * still shifts the page when the real grid arrives; it just looks handled.
 *
 * 🚨 How many cards come back is a property of THIS forum, so the count comes
 * from what the browser saw last time. A first visit shows one row and settles;
 * every visit after that reserves the right space. Storage access is wrapped
 * because a browser in private mode, or one told to block site data, throws on
 * read rather than returning null.
 */
const COUNT_KEY = 'ernestdefoe-projects.count';

const DEFAULT_COUNT = 3;

export function rememberCount(n: number): void {
  // A grid that rendered empty is not worth learning from — it would train the
  // skeleton to reserve nothing on a forum that simply had a slow first load.
  if (n < 1) return;

  try {
    localStorage.setItem(COUNT_KEY, String(n));
  } catch {
    // Storage unavailable; the skeleton falls back to the default.
  }
}

function recalledCount(): number {
  try {
    const n = Number(localStorage.getItem(COUNT_KEY));

    // Cap it: a hand-edited or stale value would otherwise reserve screens of
    // empty page, which is worse than the default.
    return Number.isFinite(n) && n >= 1 ? Math.min(12, n) : DEFAULT_COUNT;
  } catch {
    return DEFAULT_COUNT;
  }
}

export default class ProjectsSkeleton extends Component {
  view() {
    return m(
      '.ProjectsSkeleton',
      { 'aria-hidden': 'true' },
      Array.from({ length: recalledCount() }, (_, i) =>
        m('.ProjectsSkeleton-card', { key: i }, [
          m('.ProjectsSkeleton-media'),
          m('.ProjectsSkeleton-body', [
            m('.ProjectsSkeleton-bar.ProjectsSkeleton-bar--title'),
            m('.ProjectsSkeleton-bar.ProjectsSkeleton-bar--line'),
            m('.ProjectsSkeleton-bar.ProjectsSkeleton-bar--line'),
            m('.ProjectsSkeleton-bar.ProjectsSkeleton-bar--short'),
            m('.ProjectsSkeleton-bar.ProjectsSkeleton-bar--foot'),
          ]),
        ])
      )
    );
  }
}

/**
 * A single project while it loads. No remembered size here on purpose — every
 * project is a different length, so the last one says nothing about the next.
 * It under-fills rather than over-fills: under-filling settles the page upward,
 * while over-filling drops the reader's scroll position out from under them.
 */
export class ProjectSkeleton extends Component {
  view() {
    return m('.ProjectsSkeleton.ProjectsSkeleton--detail', { 'aria-hidden': 'true' }, [
      m('.ProjectsSkeleton-media.ProjectsSkeleton-media--wide'),
      m('.ProjectsSkeleton-bar.ProjectsSkeleton-bar--heading'),
      m('.ProjectsSkeleton-bar.ProjectsSkeleton-bar--meta'),
      ...[0, 1, 2].map((p) =>
        m('.ProjectsSkeleton-para', { key: p }, [
          m('.ProjectsSkeleton-bar.ProjectsSkeleton-bar--line'),
          m('.ProjectsSkeleton-bar.ProjectsSkeleton-bar--line'),
          m('.ProjectsSkeleton-bar.ProjectsSkeleton-bar--short'),
        ])
      ),
    ]);
  }
}
