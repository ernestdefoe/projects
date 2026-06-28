import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import type { Project } from '../../common/api';
import { authorAvatar } from '../authorAvatar';

declare const m: any;
const t = (k: string, p?: any): any => app.translator.trans('ernestdefoe-projects.forum.' + k, p);

/**
 * A single project card: optional image, category badges, title, author,
 * on-card custom fields, excerpt, link buttons and a like toggle. Clicking the
 * body navigates to the project detail page; interactive controls stop the
 * click from bubbling.
 */
export default class ProjectCard extends Component {
  excerptExpanded = false;

  view() {
    const p: Project = this.attrs.project;
    const onLike: (p: Project) => void = this.attrs.onLike;
    const accent = p.primaryCategory?.color || 'var(--primary-color)';

    return m(
      '.ProjectCard',
      { style: { '--project-accent': accent }, onclick: () => this.open(p) },
      [
        p.image
          ? m('.ProjectCard-image', m('img', { src: p.image, alt: p.title, loading: 'lazy' }))
          : m('.ProjectCard-image.ProjectCard-image--placeholder', m('i', { className: p.primaryCategory?.icon || 'fas fa-cube' })),

        p.status !== 'published'
          ? m('span.ProjectCard-status.ProjectCard-status--' + p.status, t('status.' + p.status))
          : null,

        m('.ProjectCard-body', [
          p.categories.length
            ? m(
                '.ProjectCard-cats',
                p.categories.slice(0, 3).map((c) =>
                  m('span.ProjectCard-cat', { style: c.color ? { '--project-accent': c.color } : undefined }, [
                    c.icon ? m('i', { className: c.icon }) : null,
                    ' ',
                    c.name,
                  ])
                )
              )
            : null,

          m('h3.ProjectCard-title', p.title),

          (() => {
            const people = [...(p.author ? [p.author] : []), ...(p.coAuthors || [])];
            return people.length
              ? m('.ProjectCard-byline', people.map((person: any, i: number) => {
                  const name = person.displayName || person.name || person.username;
                  return [
                    i > 0 ? m('span.ProjectCard-bylineSep', ', ') : null,
                    person.username
                      ? m('a.ProjectCard-author', {
                          href: app.route('user', { username: person.username }),
                          onclick: (e: Event) => e.stopPropagation(),
                        }, [authorAvatar(person), m('span', name)])
                      : m('span.ProjectCard-author.ProjectCard-author--text', name),
                  ];
                }))
              : null;
          })(),

          this.cardFields(p),

          p.excerpt ? this.excerptBlock(p) : null,
        ]),

        m('.ProjectCard-footer', [
          m('.ProjectCard-links', this.links(p)),
          this.attrs.onFeature && p.canFeature
            ? m('button.ProjectCard-feature' + (p.isFeatured ? '.is-featured' : ''), {
                type: 'button',
                title: p.isFeatured ? t('featured') : t('feature'),
                onclick: (e: Event) => { e.stopPropagation(); this.attrs.onFeature(p); },
              }, m('i', { className: (p.isFeatured ? 'fas' : 'far') + ' fa-star' }))
            : null,
          m('button.ProjectCard-like' + (p.liked ? '.is-liked' : ''), {
            type: 'button',
            disabled: p.liked === null,
            title: t('like'),
            onclick: (e: Event) => {
              e.stopPropagation();
              onLike && onLike(p);
            },
          }, [m('i', { className: (p.liked ? 'fas' : 'far') + ' fa-heart' }), ' ', String(p.likesCount)]),
        ]),
      ]
    );
  }

  cardFields(p: Project) {
    const fields = p.fields.filter((f) => f.onCard);
    if (!fields.length) return null;

    return m(
      '.ProjectCard-fields',
      fields.slice(0, 4).map((f) =>
        m('span.ProjectCard-field', { title: f.name }, [
          f.icon ? m('i', { className: f.icon }) : null,
          ' ',
          m('span.ProjectCard-fieldName', f.name + ': '),
          m('span.ProjectCard-fieldVal', this.formatField(f)),
        ])
      )
    );
  }

  formatField(f: { type: string; value: string; prefix?: string | null; suffix?: string | null }) {
    let v = f.value;
    if (f.type === 'boolean') v = t('yes');
    return (f.prefix || '') + v + (f.suffix ? ' ' + f.suffix : '');
  }

  links(p: Project) {
    // Plain .Button (not Button--icon) so the label always shows, like the
    // detail page. The serializer guarantees a non-empty label (custom label,
    // else the button's label, else the URL), so a button is never a blank strip
    // even when it has no icon.
    return p.links.slice(0, 3).map((link) =>
      m(
        'a.Button.Button--projectLink.ProjectCard-linkBtn' + (link.isPrimary ? '.Button--primary' : ''),
        {
          href: link.url,
          target: '_blank',
          rel: 'noopener noreferrer nofollow',
          title: link.label,
          onclick: (e: Event) => e.stopPropagation(),
        },
        [link.icon ? m('i.Button-icon', { className: link.icon }) : null, m('span.Button-label', link.label)]
      )
    );
  }

  open(p: Project) {
    m.route.set(app.route('projects.show', { slug: p.slug }));
  }

  /** Short description, clamped to a few lines with an inline show-more toggle so
   *  long ones stay readable without bloating the card or leaving the page. */
  excerptBlock(p: Project) {
    const long = (p.excerpt || '').length > 140;
    return m('.ProjectCard-excerptWrap', [
      m('p.ProjectCard-excerpt' + (long && !this.excerptExpanded ? '.is-clamped' : ''), p.excerpt),
      long
        ? m(
            'button.ProjectCard-excerptToggle',
            {
              type: 'button',
              onclick: (e: Event) => {
                e.stopPropagation();
                this.excerptExpanded = !this.excerptExpanded;
              },
            },
            this.excerptExpanded ? t('show_less') : t('show_more')
          )
        : null,
    ]);
  }
}
