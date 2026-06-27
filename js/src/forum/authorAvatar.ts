import type { ProjectAuthor } from '../common/api';

declare const m: any;

/**
 * Render an author's avatar, falling back to a coloured initial when the user
 * has no uploaded avatar — avoids a broken <img> with an empty src (the cause of
 * the "broken images" on cards/detail for avatar-less authors).
 */
export function authorAvatar(author: ProjectAuthor, className = 'ProjectCard-avatar') {
  if (author.avatarUrl) {
    return m('img', { className, src: author.avatarUrl, alt: '' });
  }

  return m(
    'span',
    { className: className + ' ProjectAvatar--initial' },
    (author.displayName || '?').charAt(0).toUpperCase()
  );
}
