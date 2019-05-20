'use strict';

import React from 'react';
import { format } from 'date-fns';
import ScrollableAnchor from 'react-scrollable-anchor';

import FluidCommentContent from './FluidCommentContent';
import FluidCommentForm from './FluidCommentForm';
import FluidCommentAction from './FluidCommentAction';
import FluidCommentAuthor from './FluidCommentAuthor';

import { getDeepProp, getResponseDocument, getFormKey, formatBodyAdd, formatBodyUpdate } from './functions.js';
import { getMetaFromRel } from './routes.js';

function getSelfTitle(method) {
  const titles = {
    'PATCH': 'Edit',
    'DELETE': 'Delete'
  };

  return titles[method] ? titles[method] : '';
}

function getLinkTitles(key) {
  const titles = {
    'reply': 'Reply',
    'publish': 'Approve',
    'unpublish': 'Unpublish'
  };

  return titles[key] ? titles[key] : '';
}

function processLink({ name, title, href, method, data = {} }) {
  const options = { method };

  return {
    className: `comment-${title.toLowerCase()}`,
    name,
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

    const meta = getMetaFromRel(rel);

    meta.forEach(relMeta => {
      const { alias, method } = relMeta;
      const name = key === 'self' ? alias : key;
      const title = key === 'self'
        ? getSelfTitle(method)
        : getLinkTitles(key);

      processed.push(processLink({ name, title, href, method, data }));
    });
  });

  return processed;
}

class FluidComment extends React.Component {

  constructor(props) {
    super(props);

    this.state = {
      formKey: getFormKey(props.comment.id),
      action: null
    }
  }

  resetForm = () => {
    this.setState({ formKey: getFormKey(this.props.comment.id) });
  };

  render() {
    const { comment, isRefreshing, children } = this.props;
    const { action, formKey } = this.state;

    const cid = getDeepProp(comment, 'attributes.drupal_internal__cid');
    const subject = getDeepProp(comment, 'attributes.subject');
    const body = getDeepProp(comment, 'attributes.comment_body.processed');
    const published = getDeepProp(comment, 'attributes.status');
    const created = getDeepProp(comment, 'attributes.created');
    const changed = getDeepProp(comment, 'attributes.changed');
    const links = processLinks(comment.links);

    const author = {
      name: getDeepProp(comment, 'user.attributes.name'),
      image: getDeepProp(comment, 'user.picture.attributes.uri.url'),
      url: getDeepProp(comment, 'user.url')
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

    const dateFormat = 'ddd, MM/DD/YYYY';
    const permaLink = `comment-${cid}`;

    return (
      <>
        <ScrollableAnchor id={permaLink}>
          <article role="article" className={classes.article.join(' ')}>
            <span className="hidden" data-comment-timestamp={format(new Date(changed), 'X')}></span>
            <footer className="comment__meta">
              <FluidCommentAuthor author={author} />
              <p className="comment__time">{format(new Date(created), dateFormat)}</p>
              <p className="comment__permalink"><a href={`#${permaLink}`}>Permalink</a></p>
              <p className="visually-hidden">parent</p>
            </footer>
            {action !== null && action.name !== 'reply'
              ? <FluidCommentAction
                  name={action.name}
                  title={action.title}
                  subject={subject}
                  body={body}
                  handleEdit={this.saveComment}
                  handleConfirm={this.commentConfirm}
                  handleCancel={this.commentCancel}
                  formKey={`${formKey}-edit`}
                  isRefreshing={isRefreshing}
                />
              : <FluidCommentContent
                  id={comment.id}
                  subject={subject}
                  body={body}
                  classes={classes}
                  links={links}
                  permaLink={permaLink}
                  action={this.commentAction}
                />}
          </article>
        </ScrollableAnchor>
        {(action !== null && action.name === 'reply') &&
          <FluidCommentForm
            key={formKey}
            handleSubmit={this.saveComment}
            handleCancel={this.commentCancel}
            isRefreshing={isRefreshing}
          />
        }
        {children && children.length ? <div className="indented">{children}</div> : null}
      </>
    );
  }

  formatCommentRequest = (values) => {
    const { comment } = this.props;
    const { options } = this.state.action;

    const { id, type } = comment;
    let body = {};

    switch (options.method) {
      case 'POST':
        const node = getDeepProp(comment, 'relationships.entity_id.data');
        const field = getDeepProp(comment, 'attributes.field_name');
        body = formatBodyAdd(values, node, field, type);
        break;

      case 'PATCH':
        body = formatBodyUpdate(values, id, type);
        break;

      default:
        console.error(`Method ${options.method} not handled`);
        break;
    }

    return Object.assign(options, { body });
  };

  saveComment = (values) => {
    const { refresh } = this.props;
    const { href } = this.state.action;
    const request = this.formatCommentRequest(values);

    getResponseDocument(href, request).then(() => {
      this.setState({ action: null });
      refresh();
    });
  };

  commentConfirm = () => {
    const { refresh } = this.props;
    const { href, data, options } = this.state.action;

    if (Object.keys(data).length) {
      options.body = JSON.stringify({ data });
    }

    getResponseDocument(href, options).then(() => {
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
