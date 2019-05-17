'use strict';
import React from 'react';
import { getDeepProp, getResponseDocument, formatRequest, getFormKey } from './functions';
import { getRelUri, objectHasLinkWithRel } from './routes';

import FluidCommentList from './FluidCommentList';
import FluidCommentForm from './FluidCommentForm';

class FluidCommentWrapper extends React.Component {

  constructor(props) {
    super(props);
    this.state = {
      comments: [],
      isRefreshing: false,
      threaded: false,
      formKey: getFormKey()
    };
  }

  componentDidMount() {
    this.refreshComments();
  }

  render() {
    const { currentNode } = this.props;
    const { comments, isRefreshing, threaded, formKey } = this.state;
    const addLink = getRelUri('add');
    const hasLink = objectHasLinkWithRel(currentNode, 'comments', addLink);

    const show = currentNode && objectHasLinkWithRel(currentNode, 'comments', getRelUri('collection'));

    return (
      <div>
      {show &&
        <FluidCommentList
          threaded={threaded}
          comments={comments}
          isRefreshing={isRefreshing}
          refresh={this.refreshComments}
        />
      }

      {hasLink &&
        <div>
          <h2 className="title comment-form__title">Add new comment</h2>
          <FluidCommentForm
            key={formKey}
            handleSubmit={this.addComment}
            isRefreshing={isRefreshing}
          />
        </div>
      }
      </div>
    );
  }

  addComment = (values) => {
    const { currentNode: node, commentType: type } = this.props;
    const commentsUrl = getDeepProp(node, 'links.comments.href');
    const field = getDeepProp(node, 'links.comments.meta.commentFieldName');
    const request = formatRequest(values, 'POST', node, type, field);

    if (objectHasLinkWithRel(node, 'comments', getRelUri('add'))) {
      getResponseDocument(commentsUrl, request).then(doc => {
        if (!doc.errors) {
          this.refreshComments();
          this.resetForm();
        }
      });
    }
  };

  refreshComments = () => {
    const { currentNode: node } = this.props;
    const commentsUrl = getDeepProp(node, 'links.comments.href');

    if (objectHasLinkWithRel(node, 'comments', getRelUri('collection'))) {
      this.getAndAddComments(`${commentsUrl}/?include=uid.user_picture`);
    }
  };

  resetForm = () => {
    this.setState({ formKey: getFormKey() });
  };

  /**
   * @todo Replace with serializing
   */
  mergeIncluded(comments, included) {
    return comments.map(comment => {

      const { uid } = comment.relationships;
      const users = included.filter(item => item.id === uid.data.id);

      if (users.length > 0) {
        let user = users[0];
        const pic = getDeepProp(user, 'relationships.user_picture.data');
        if (pic) {
          const pictures = included.filter(item => item.id === pic.id);
          if (pictures.length > 0) {
            user = Object.assign(user, { picture: pictures[0] });
          }
        }

        return Object.assign(comment, { user });
      }

      return comment;
    })
  }

  getAndAddComments(commentsUrl, previous = []) {
    this.setState({ isRefreshing: true });

    getResponseDocument(commentsUrl).then(doc => {
      this.setState({threaded: getDeepProp(doc, 'meta.displayOptions.threaded')});
      const data = getDeepProp(doc, 'data');
      const included = getDeepProp(doc, 'included');
      const nextUrl = getDeepProp(doc, 'links.next.href');

      const comments = [...previous, ...this.mergeIncluded(data, included)];

      if (nextUrl) {
        this.getAndAddComments(nextUrl, comments);
      }

      this.setState({ comments, isRefreshing: false });
    });
  }

}

export default FluidCommentWrapper;
