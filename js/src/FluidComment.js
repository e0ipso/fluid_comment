'use strict';

import React from 'react';
import FluidCommentContent from './FluidCommentContent';
import FluidCommentAction from './FluidCommentAction';
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
    this.state = {
      action: null
    }
  }

  render() {
    const { comment, isRefreshing, children } = this.props;
    const { action } = this.state;

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
      <React.Fragment>
        <article role="article" className={classes.article.join(' ')}>
          <span className="hidden" data-comment-timestamp="{{ new_indicator_timestamp }}"></span>
          <footer className="comment__meta">
            {author.image && <img src={author.image} alt={author.name} />}
            <p className="comment__author">{author.name}</p>
            <p className="comment__time">created</p>
            <p className="comment__permalink">permalink</p>
            <p className="visually-hidden">parent</p>
          </footer>
          {action !== null
            ? <FluidCommentAction
                title={action.title}
                confirm={this.commentConfirm}
                cancel={this.commentCancel}
              />
            : <FluidCommentContent
                id={comment.id}
                subject={subject}
                body={body}
                classes={classes}
                links={links}
                action={this.commentAction}
              />}
        </article>
        {children && children.length ? <div className="indented">{children}</div> : null}
      </React.Fragment>
    );
  }

  commentConfirm = () => {
    const { refresh } = this.props;
    const { href, data, options } = this.state.action;

    if (Object.keys(data).length) {
      options.body = JSON.stringify({ data });
    }

    getResponseDocument(href, options).then(() => {
      // console.log(`Called ${link.title} with ${options.method} for ${href}`);
      this.setState({ action: null });
      refresh();
    });
  };

  commentCancel = () => {
    this.setState({ action: null })
  };

  commentAction = (event, link) => {
    const { isRefreshing } = this.props;

    event.preventDefault();

    if (isRefreshing) {
      return;
    }

    // Copy link to avoid affecting it when resetting state.
    this.setState({ action: Object.assign({}, link) });
  }

}

export default FluidComment;
