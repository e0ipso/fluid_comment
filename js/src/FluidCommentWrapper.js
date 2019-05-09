'use strict';
import React from 'react';
import { getDeepProp, getResponseDocument } from './functions';
import FluidComment from './FluidComment';
import FluidCommentForm from './FluidCommentForm';
import InlineLoginForm from "./InlineLoginForm";

class FluidCommentWrapper extends React.Component {

  constructor(props) {
    super(props);
    this.state = { loggedIn: this.props.loginUrl ? false : null, comments: [] };
  }

  componentDidMount() {
    this.refreshComments();
  }

  filteredCommentsUrl() {
    const { hostId, commentsUrl } = this.props;
    const id = 'entity_id.id';
    const include = 'uid.user_picture';

    return `${commentsUrl}/?filter[${id}]=${hostId}&include=${include}`;
  }

  render() {
    const content = [];
    if (this.state.comments.length) {

      content.push(this.state.comments.map((comment, index) => (
        <FluidComment
          key={comment.id}
          index={index}
          comment={comment}
          refresh={() => this.refreshComments()}
        />
      )));
    }

    if (this.state.loggedIn === false) {
      const onLogin = (success) => {
        this.setState({loggedIn: !!success});
        this.refreshComments();
      };

      content.push((
          <div>
            <h3>Log in to comment:</h3>
            <InlineLoginForm key="loginForm" loginUrl={this.props.loginUrl} onLogin={onLogin} />
          </div>
      ));
    }
    else if (this.props.currentNode) {
      content.push((<FluidCommentForm key="commentForm" commentTarget={this.props.currentNode} commentType={this.props.commentType} commentsUrl={this.props.commentsUrl} onSubmit={() => this.refreshComments()}/>));
    }
    return content;
  }

  refreshComments() {
    this.getAndAddComments(this.filteredCommentsUrl());
  }

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
    getResponseDocument(commentsUrl).then(doc => {
      const data = getDeepProp(doc, 'data');
      const included = getDeepProp(doc, 'included');
      const nextUrl = getDeepProp(doc, 'links.next.href');

      const comments = [...previous, ...this.mergeIncluded(data, included)];

      if (nextUrl) {
        this.getAndAddComments(nextUrl, comments);
      }

      this.setState({ comments });
    });
  }

}

export default FluidCommentWrapper;
