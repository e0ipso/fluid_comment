'use strict';

import React from 'react';
import FluidCommentLink from './FluidCommentLink'
import { getDeepProp, getResponseDocument } from './functions.js';

function getLinkTitles(key) {
  const titles = {
    'update': 'Edit',
    'delete': 'Delete',
    'publish': 'Approve',
    'unpublish': 'Unpublish'
  };

  return titles[key];
}
function getLinkOptions(action) {
  const methods = {
    'update': 'PATCH',
    'delete': 'DELETE'
  };

  return { method: methods[action] }
}

function processLink({ title, href, method, data = {} }) {
  return {
    title: getLinkTitles(title),
    options: getLinkOptions(method),
    className: `comment-${title.toLowerCase()}`,
    href,
    data
  }
}
function processLinks(links) {
  let processed = [];

  Object.keys(links).forEach(key => {

    let rel = [];
    let data = {};
    let title = '';
    const href = links[key].href;

    ['update', 'delete'].forEach(method => {
      if (key === 'self') {
        title = method;
        rel = getDeepProp(links[key], 'meta.linkRel');
      }
      else {
        const params = getDeepProp(links[key], 'meta.linkParams');
        rel = params.rel;
        data = params.data;
        title = key;
      }

      if (rel.find(value => value.match(new RegExp(`${method}$`)))) {
        processed.push(processLink({ title, href, method, data }));
      }
    });
  });

  return processed;
}

class FluidComment extends React.Component {

  constructor(props) {
    super(props);
  }

  render() {
    const { comment } = this.props;

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
                <li className={link.className}>
                  <FluidCommentLink link={link} handleClick={(e) => this.commentAction(e, link)} />
                </li>
              ))}
            </ul>}

          </div>
        </article>
    );
  }

  commentAction = (event, link) => {
    event.preventDefault();
    const { href, data, options } = link;

    if (Object.keys(data).length) {
      options.body = JSON.stringify({ data });
    }

    getResponseDocument(href, options).then(() => {
      console.log(`Called ${link.title} with ${options.method} for ${href}`);
      this.props.refresh();
    });
  }
}

export default FluidComment;
