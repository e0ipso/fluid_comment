'use strict';

import React from 'react';
import { getDeepProp, getResponseDocument } from './functions.js';

class FluidComment extends React.Component {

  constructor(props) {
    super(props);
    this.deleteComment = this.deleteComment.bind(this);
  }

  render() {
    const comment = this.props.comment;
    const subject = getDeepProp(comment, 'attributes.subject');
    const body = getDeepProp(comment, 'attributes.comment_body.processed');
    const published = getDeepProp(comment, 'attributes.status');
    const selfLink = getDeepProp(comment, 'links.self');
    const deletable = selfLink.hasOwnProperty('rel') && selfLink['rel'].includes('delete');
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
          </div>
          {deletable && <ul className="links inline">
            <li className="comment-delete">
              <a href="_blank" onClick={this.deleteComment}>Delete</a>
            </li>
          </ul>}
        </article>
    );
  }

  deleteComment(event) {
    event.preventDefault();
    getResponseDocument(getDeepProp(this.props.comment, 'links.self.href'), {method: 'DELETE'}).then(() => {
      this.props.onDelete();
    });
  }

}

export default FluidComment;
