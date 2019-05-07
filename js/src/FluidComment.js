'use strict';

import React from 'react';
import FluidCommentLink from './FluidCommentLink'
import { getDeepProp, getResponseDocument } from './functions.js';
import { getMethodsFromRel } from './routes.js';

function getSelfTitle(method) {
  const titles = {
    'PATCH': 'Edit',
    'DELETE': 'Delete'
  };

  return titles[method] ? titles[method] : '';
}

function getLinkTitles(key) {
  const titles = {
    'publish': 'Approve',
    'unpublish': 'Unpublish'
  };

  return titles[key] ? titles[key] : '';
}

function processLink({ title, href, method, data = {} }) {
  const options = { method };

  return {
    className: `comment-${title.toLowerCase()}`,
    title,
    options,
    href,
    data
  }
}

function processLinks(links) {
  let processed = [];

  Object.keys(links).forEach(key => {

    const link = links[key];
    const params = getDeepProp(link, 'meta.linkParams');
    const { href } = link;
    const { rel } = params;
    const data = params.data ? params.data : {};

    const methods = getMethodsFromRel(rel);

    methods.forEach(method => {
      const title = key === 'self'
        ? getSelfTitle(method)
        : getLinkTitles(key);

      processed.push(processLink({ title, href, method, data }));
    });
  });

  return processed;
}

class FluidComment extends React.Component {

  constructor(props) {
    super(props);
  }

  render() {
    const { comment, isRefreshing } = this.props;

    const subject = getDeepProp(comment, 'attributes.subject');
    const body = getDeepProp(comment, 'attributes.comment_body.processed');
    const published = getDeepProp(comment, 'attributes.status');
    const links = processLinks(comment.links);

    const author = {
      name: getDeepProp(comment, 'user.attributes.name'),
      image: getDeepProp(comment, 'user.picture.attributes.uri.url')
    };

    const classes = {
      article: [
        'comment',
        'js-comment',
        !published && `comment--unpublished`,
        // 'by-anonymous'
        // 'by-' ~ commented_entity.getEntityTypeId() ~ '-author'
        'clearfix'
      ],
      content: [
        'text-formatted',
        'field',
        'field--name-comment-body',
        'field--type-text-long',
        'field--label-hidden',
        'field__item',
        'clearfix'
      ]
    };

    return (
        <article role="article" className={classes.article.join(' ')}>
          <span className="hidden" data-comment-timestamp="{{ new_indicator_timestamp }}"></span>
          <footer className="comment__meta">
            {author.image && <img src={author.image} alt={author.name} />}
            <p className="comment__author">{author.name}</p>
            <p className="comment__time">created</p>
            <p className="comment__permalink">permalink</p>
            <p className="visually-hidden">parent</p>
          </footer>
          <div className="comment__content">
            <h3>{ subject }</h3>
            <div
              className={classes.content.join(' ')}
              dangerouslySetInnerHTML={{__html: body}}>
            </div>
            {links && <ul className="links inline">
              {links.map(link => (
                <li key={`${comment.id}-${link.className}`} className={link.className}>
                  <FluidCommentLink
                    link={link}
                    handleClick={(e) => this.commentAction(e, link)}
                  />
                </li>
              ))}
            </ul>}

          </div>
        </article>
    );
  }

  commentAction = (event, link) => {
    const { isRefreshing, refresh } = this.props;

    event.preventDefault();

    if (isRefreshing) {
      return;
    }

    const { href, data, options } = link;

    if (Object.keys(data).length) {
      options.body = JSON.stringify({ data });
    }

    getResponseDocument(href, options).then(() => {
      // console.log(`Called ${link.title} with ${options.method} for ${href}`);
      refresh();
    });
  }
}

export default FluidComment;
