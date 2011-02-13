function(doc) {
    if (doc.doctrine_metadata.type == 'Doctrine.Tests.Models.CMS.CmsUser') {
        emit(doc.username, doc._id);
    }
}