'use strict';
import React from 'react';
import { getDeepProp, getResponseDocument } from './functions';
import { getRelUri, objectHasLinkWithRel } from './routes';
import FluidComment from './FluidComment';
import FluidCommentForm from './FluidCommentForm';
import InlineLoginForm from "./InlineLoginForm";

class FluidCommentWrapper extends React.Component {

  constructor(props) {
    super(props);
    this.state = {
      loggedIn: this.props.loginUrl ? false : null,
      comments: [],
      isRefreshing: false
    };
  }

  componentDidMount() {
    this.refreshComments();
  }

  onLogin = (success) => {
    this.setState({loggedIn: !!success});
    this.refreshComments();
  };

  render() {
    const { currentNode, loginUrl, commentType } = this.props;
    const { comments, loggedIn, isRefreshing } = this.state;
    const addLink = getRelUri('add');
    const hasLink = objectHasLinkWithRel(currentNode, 'comments', addLink);

    return (
      <div>
      {comments.map((comment, index) => (
        <FluidComment
          key={comment.id}
          index={index}
          comment={comment}
          refresh={() => this.refreshComments()}
          isRefreshing={isRefreshing}
        />
      ))}

      {loggedIn === false
        ? <div>
            <h2 className="title comment-form__title">Log in to comment</h2>
            <InlineLoginForm
              key="loginForm"
              loginUrl={loginUrl}
              onLogin={this.onLogin}
            />
          </div>
        : hasLink && <div>
            <h2 className="title comment-form__title">Add new comment</h2>
            <FluidCommentForm
              key="commentForm"
              commentTarget={currentNode}
              commentType={commentType}
              onSubmit={() => this.refreshComments()}
              isRefreshing={isRefreshing}
            />
          </div>
      }
      </div>
    );
  }

  refreshComments() {
    if (objectHasLinkWithRel(this.props.currentNode, 'comments', getRelUri('collection'))) {
      this.getAndAddComments(`${this.props.currentNode.links.comments.href}/?include=uid.user_picture`);
    }
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
    this.setState({ isRefreshing: true });

    getResponseDocument(commentsUrl).then(doc => {
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
